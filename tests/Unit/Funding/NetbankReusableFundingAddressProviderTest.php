<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Contracts\StandingFundingAddressProvider;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressRequestData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingObservationRequestData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use LBHurtado\PaymentGateway\Funding\NetbankReusableFundingAddressProvider;

beforeEach(function () {
    Cache::clear();
    Http::preventStrayRequests();

    config()->set('payment-gateway.netbank.funding', [
        'api_url' => 'https://api.netbank.test',
        'token_url' => 'https://auth.netbank.test/oauth2/token',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'corporate_account_number' => '113001000019',
        'vca_alias' => '91500',
        'reference_key' => 'funding-reference-key',
        'qr_endpoint' => 'https://api.netbank.test/v1/qrph/generate',
        'qr_merchant_name' => 'X Change',
        'qr_merchant_city' => 'Manila',
        'qr_resolution' => 480,
        'connect_timeout_seconds' => 5,
        'timeout_seconds' => 10,
        'verification_lookback_days' => 7,
    ]);
});

it('creates a stable open-amount static NetBank QR without registering or limiting a VCA', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => reusableFundingValidPngBase64(),
        ]),
    ]);

    $provider = app(NetbankReusableFundingAddressProvider::class);
    $first = $provider->create('App\\Models\\User:5');
    $second = $provider->create('App\\Models\\User:5');

    expect($first->provider)->toBe('netbank')
        ->and($first->fundingAddress)->toMatch('/\A91500\d{16}\z/')
        ->and($second->fundingAddress)->toBe($first->fundingAddress)
        ->and($first->currency)->toBe('PHP')
        ->and($first->institution)->toBe('NetBank')
        ->and($first->merchantName)->toBe('X Change')
        ->and($first->qrCode->qrMode)->toBe('static')
        ->and($first->qrCode->transactionType)->toBe('p2m')
        ->and($first->qrCode->embeddedAmount)->toBeFalse()
        ->and($first->qrCode->providerGenerated)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.netbank.test/v1/qrph/generate'
        && $request->data() === [
            'merchant_name' => 'X Change',
            'merchant_city' => 'Manila',
            'qr_type' => 'Static',
            'qr_transaction_type' => 'P2M',
            'destination_account' => $first->fundingAddress,
            'resolution' => 480,
            'amount' => ['cur' => 'PHP', 'num' => ''],
        ]);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/pre-transaction/'));
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/vca/create'));
});

it('implements the provider-neutral standing address contract with purpose-separated addresses', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => reusableFundingValidPngBase64(),
        ]),
    ]);

    $provider = app(NetbankReusableFundingAddressProvider::class);
    $accountFunding = $provider->createStandingFundingAddress(standingAddressRequest());
    $payment = $provider->createStandingFundingAddress(standingAddressRequest(
        FundingAddressPurpose::Payment,
    ));

    expect($provider)->toBeInstanceOf(StandingFundingAddressProvider::class)
        ->and($provider->providerCode())->toBe('netbank')
        ->and($accountFunding->purpose)->toBe(FundingAddressPurpose::AccountFunding)
        ->and($accountFunding->accountReference)->toBe('App\\Models\\User:5')
        ->and($accountFunding->fundingAddress)->toMatch('/\A91500\d{16}\z/')
        ->and($accountFunding->fundingAddress)->not->toBe($payment->fundingAddress)
        ->and($accountFunding->qrCode->qrMode)->toBe('static')
        ->and($accountFunding->qrCode->embeddedAmount)->toBeFalse()
        ->and($accountFunding->reusable)->toBeTrue();
});

it('returns only sanitized incoming observations for the exact reusable VCA', function () {
    $fundingAddress = reusableFundingAddressForOwner('App\\Models\\User:5');

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [
                reusableFundingTransaction(destination: $fundingAddress),
                reusableFundingTransaction(
                    transactionId: 'wrong-destination',
                    destination: '915009999999999999999',
                ),
                reusableFundingTransaction(
                    transactionId: 'outgoing',
                    destination: $fundingAddress,
                    type: 'Debit',
                ),
            ],
        ]),
    ]);

    $provider = app(NetbankReusableFundingAddressProvider::class);
    $observations = $provider->observationsForOwner('App\\Models\\User:5');

    expect($observations)->toHaveCount(1)
        ->and($observations[0]->transactionHash)->toBe(hash('sha256', 'transaction-123'))
        ->and($observations[0]->transactionHash)->not->toContain('transaction-123')
        ->and($observations[0]->grossAmountMinor)->toBe(25_000)
        ->and($observations[0]->feeAmountMinor)->toBe(50)
        ->and($observations[0]->netAmountMinor)->toBe(24_950)
        ->and($observations[0]->currency)->toBe('PHP')
        ->and($observations[0]->providerStatus)->toBe('settled')
        ->and($observations[0]->occurredAt?->format(DATE_ATOM))->toBe('2026-07-23T01:05:00+00:00')
        ->and($observations[0]->settledAt?->format(DATE_ATOM))->toBe('2026-07-23T01:06:00+00:00');

    expect((array) $observations[0])
        ->not->toHaveKeys(['transaction_id', 'sender', 'account_number', 'raw_payload']);

    Http::assertSent(fn (Request $request): bool => str_contains(
        $request->url(),
        '/v1/vca/'.$fundingAddress.'/transactions?',
    ) && $request->data() === [
        'account_number' => '113001000019',
        'limit' => 100,
        'offset' => 0,
    ]);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/qrph/generate'));
});

it('returns authoritative provider observations internally for standing address settlement', function () {
    $provider = app(NetbankReusableFundingAddressProvider::class);
    $address = standingFundingAddressForPurpose(FundingAddressPurpose::AccountFunding);

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [
                reusableFundingTransaction(destination: $address),
                reusableFundingTransaction(
                    transactionId: 'wrong-destination',
                    destination: '915009999999999999999',
                ),
            ],
        ]),
    ]);

    $observations = $provider->observeStandingFundingAddress(
        new StandingFundingObservationRequestData(
            fundingAddress: $address,
            accountReference: 'App\\Models\\User:5',
            purpose: FundingAddressPurpose::AccountFunding,
            currency: 'PHP',
            verificationSource: 'operator',
            webhookReceiptId: 42,
        ),
    );

    expect($observations)->toHaveCount(1)
        ->and($observations[0]->providerTransactionId)->toBe('transaction-123')
        ->and($observations[0]->providerOperationId)->toBeNull()
        ->and($observations[0]->requestId)->toBeNull()
        ->and($observations[0]->grossAmountMinor)->toBe(25_000)
        ->and($observations[0]->netAmountMinor)->toBe(24_950)
        ->and($observations[0]->fundingAddress)->toBe('sha256:'.hash('sha256', $address))
        ->and($observations[0]->providerAccountReference)
        ->toBe('sha256:'.hash('sha256', '113001000019'))
        ->and($observations[0]->webhookReceiptId)->toBe(42)
        ->and($observations[0]->metadata)->toBe([
            'description' => 'EXTERNAL_TRANSFER_INCOMING',
            'type' => 'Credit',
            'settlement_rail' => null,
            'destination_verified' => true,
            'address_purpose' => 'account_funding',
            'expected_currency_matches' => true,
            'trigger' => 'operator',
        ])
        ->and($observations[0]->metadata)
        ->not->toHaveKeys(['sender', 'account_number', 'raw_payload']);
});

it('fails closed when a reusable QR response is not a valid PNG', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => base64_encode('not-a-png'),
            'secret' => 'must-not-leak',
        ]),
    ]);

    expect(fn () => app(NetbankReusableFundingAddressProvider::class)->create('App\\Models\\User:5'))
        ->toThrow(NetbankFundingRequestFailed::class, 'generate-qrph');
});

/**
 * @return array<string, mixed>
 */
function reusableFundingTransaction(
    string $transactionId = 'transaction-123',
    string $destination = '915001234567890123456',
    string $type = 'Credit',
): array {
    return [
        'amount' => ['cur' => 'PHP', 'num' => '25000'],
        'date' => '2026-07-23T01:05:00.000Z',
        'description' => 'EXTERNAL_TRANSFER_INCOMING',
        'destination_account' => [
            'account_alias' => $destination,
            'account_number' => 'sensitive-destination-account',
        ],
        'fees' => [['amount' => ['cur' => 'PHP', 'num' => '50']]],
        'sender' => [
            'name' => 'Sensitive Sender',
            'account_number' => 'sensitive-sender-account',
        ],
        'status' => 'Settled',
        'status_details' => [[
            'status' => 'Settled',
            'updated' => '2026-07-23T01:06:00.000Z',
        ]],
        'transaction_id' => $transactionId,
        'type' => $type,
        'updated' => '2026-07-23T01:06:00.000Z',
    ];
}

function reusableFundingValidPngBase64(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==';
}

function reusableFundingAddressForOwner(string $ownerReference): string
{
    $digest = hash_hmac(
        'sha256',
        'reusable-funding-address|'.$ownerReference,
        'funding-reference-key',
        true,
    );
    $numeric = '';

    for ($index = 0; $index < 16; $index++) {
        $numeric .= (string) (ord($digest[$index]) % 10);
    }

    return '91500'.$numeric;
}

function standingAddressRequest(
    FundingAddressPurpose $purpose = FundingAddressPurpose::AccountFunding,
): StandingFundingAddressRequestData {
    return new StandingFundingAddressRequestData(
        ownerReference: 'App\\Models\\User:5',
        accountReference: 'App\\Models\\User:5',
        purpose: $purpose,
        currency: 'PHP',
    );
}

function standingFundingAddressForPurpose(FundingAddressPurpose $purpose): string
{
    $reference = implode('|', [
        'standing-funding-address',
        'App\\Models\\User:5',
        'App\\Models\\User:5',
        $purpose->value,
        'PHP',
    ]);

    return reusableFundingAddressForOwner($reference);
}
