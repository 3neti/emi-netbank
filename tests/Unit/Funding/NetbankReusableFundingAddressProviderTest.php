<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Contracts\StandingFundingAddressProvider;
use LBHurtado\EmiCore\Data\Funding\FundingQrMerchantData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressRequestData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingObservationRequestData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingConfigurationException;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use LBHurtado\PaymentGateway\Funding\NetbankReusableFundingAddressProvider;
use LBHurtado\PaymentGateway\Funding\NetbankStandingAddressProfile;

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
        'standing_address' => [
            'scheme' => 'netbank-mobile-v1',
            'reference_length' => 11,
            'hmac_key_id' => null,
            'hmac_key' => null,
        ],
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
    $first = $provider->create('App\\Models\\User:5', routingReference: '09173011987');
    $second = $provider->create('App\\Models\\User:5', routingReference: '09173011987');

    expect($first->provider)->toBe('netbank')
        ->and($first->fundingAddress)->toBe('9150009173011987')
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

it('maps provider-neutral merchant metadata into the reusable QR payload', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => reusableFundingValidPngBase64(),
        ]),
    ]);

    $address = app(NetbankReusableFundingAddressProvider::class)
        ->createStandingFundingAddress(new StandingFundingAddressRequestData(
            ownerReference: 'App\\Models\\User:5',
            accountReference: 'wallet:01JACCOUNT',
            purpose: FundingAddressPurpose::AccountFunding,
            currency: 'PHP',
            routingReference: '09173011987',
            qrMerchant: new FundingQrMerchantData(
                displayName: 'Lester Store',
                city: 'Makati',
                categoryCode: '0000',
                profileReference: 'merchant:01JMERCHANT',
                profileFingerprint: 'sha256:merchant-profile',
            ),
        ));

    expect($address->displayData)->toMatchArray([
        'merchant_name' => 'Lester Store',
        'merchant_city' => 'Makati',
        'merchant_category_code' => '0000',
        'merchant_profile_reference' => 'merchant:01JMERCHANT',
        'merchant_profile_fingerprint' => 'sha256:merchant-profile',
        'merchant_metadata_version' => 'funding-qr-merchant-v1',
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.netbank.test/v1/qrph/generate'
        && data_get($request->data(), 'merchant_name') === 'Lester Store'
        && data_get($request->data(), 'merchant_city') === 'Makati'
        && data_get($request->data(), 'destination_account') === $address->fundingAddress);
});

it('rejects invalid merchant metadata before calling NetBank', function () {
    Http::fake();

    expect(fn () => app(NetbankReusableFundingAddressProvider::class)
        ->createStandingFundingAddress(new StandingFundingAddressRequestData(
            ownerReference: 'App\\Models\\User:5',
            accountReference: 'wallet:01JACCOUNT',
            purpose: FundingAddressPurpose::AccountFunding,
            currency: 'PHP',
            routingReference: '09173011987',
            qrMerchant: new FundingQrMerchantData(
                displayName: str_repeat('X', 26),
                city: 'Manila',
            ),
        )))
        ->toThrow(
            NetbankFundingConfigurationException::class,
            'merchant name must contain 1 to 25 characters',
        );

    Http::assertNothingSent();
});

it('implements the provider-neutral standing address contract with purpose-separated addresses', function () {
    useHmacStandingAddressScheme();

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
    $accountFunding = $provider->createStandingFundingAddress(standingAddressRequest(
        routingReference: null,
    ));
    $payment = $provider->createStandingFundingAddress(standingAddressRequest(
        FundingAddressPurpose::Payment,
        routingReference: null,
    ));
    $collisionRetry = $provider->createStandingFundingAddress(standingAddressRequest(
        routingReference: null,
        derivationCounter: 1,
    ));
    $accountFundingAgain = $provider->createStandingFundingAddress(standingAddressRequest(
        routingReference: null,
    ));

    expect($provider)->toBeInstanceOf(StandingFundingAddressProvider::class)
        ->and($provider->providerCode())->toBe('netbank')
        ->and($accountFunding->purpose)->toBe(FundingAddressPurpose::AccountFunding)
        ->and($accountFunding->accountReference)->toBe('wallet:01JACCOUNT')
        ->and($accountFunding->fundingAddress)->toMatch('/\A91500\d{11}\z/')
        ->and($accountFunding->fundingAddress)->not->toBe($payment->fundingAddress)
        ->and($accountFunding->fundingAddress)->not->toBe($collisionRetry->fundingAddress)
        ->and($accountFundingAgain->fundingAddress)->toBe($accountFunding->fundingAddress)
        ->and($accountFunding->displayData['derivation_scheme'])->toBe('netbank-account-hmac-v2')
        ->and($accountFunding->displayData['derivation_key_id'])->toBe('v2-test')
        ->and($accountFunding->displayData['derivation_counter'])->toBe(0)
        ->and($collisionRetry->displayData['derivation_counter'])->toBe(1)
        ->and($accountFunding->displayData['reference_length'])->toBe(11)
        ->and($accountFunding->qrCode->qrMode)->toBe('static')
        ->and($accountFunding->qrCode->embeddedAmount)->toBeFalse()
        ->and($accountFunding->reusable)->toBeTrue();
});

it('returns only sanitized incoming observations for the exact reusable VCA', function () {
    $fundingAddress = '9150009173011987';

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [
                reusableFundingTransaction(destination: $fundingAddress),
                reusableFundingTransaction(
                    transactionId: 'wrong-destination',
                    destination: '9150099999999999',
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
    $observations = $provider->observationsForOwner(
        'App\\Models\\User:5',
        routingReference: '09173011987',
    );

    expect($observations)->toHaveCount(1)
        ->and($observations[0]->transactionHash)->toBe(hash('sha256', 'transaction-123'))
        ->and($observations[0]->transactionHash)->not->toContain('transaction-123')
        ->and($observations[0]->grossAmountMinor)->toBe(25_000)
        ->and($observations[0]->feeAmountMinor)->toBe(0)
        ->and($observations[0]->netAmountMinor)->toBe(25_000)
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
    useHmacStandingAddressScheme();

    $provider = app(NetbankReusableFundingAddressProvider::class);
    $address = standingFundingAddressForPurpose(FundingAddressPurpose::AccountFunding);

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [
                reusableFundingTransaction(destination: $address),
                reusableFundingTransaction(
                    transactionId: 'wrong-destination',
                    destination: '9150099999999999',
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
        ->and($observations[0]->feeAmountMinor)->toBe(0)
        ->and($observations[0]->netAmountMinor)->toBe(25_000)
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
            'normalization_version' => 'netbank-standing-credit-v2',
            'incoming_credit_amount_is_net_received' => true,
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

    expect(fn () => app(NetbankReusableFundingAddressProvider::class)->create(
        'App\\Models\\User:5',
        routingReference: '09173011987',
    ))
        ->toThrow(NetbankFundingRequestFailed::class, 'generate-qrph');
});

it('reopens a persisted address without recomputing it after a scheme change', function () {
    useHmacStandingAddressScheme();
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => reusableFundingValidPngBase64(),
        ]),
    ]);

    $address = app(NetbankReusableFundingAddressProvider::class)->createStandingFundingAddress(
        new StandingFundingAddressRequestData(
            ownerReference: 'App\\Models\\User:5',
            accountReference: 'wallet:01JACCOUNT',
            purpose: FundingAddressPurpose::AccountFunding,
            currency: 'PHP',
            existingFundingAddress: '9150009173011987',
        ),
    );

    expect($address->fundingAddress)->toBe('9150009173011987')
        ->and($address->displayData['derivation_scheme'])->toBeNull()
        ->and($address->displayData['reference_length'])->toBe(11);
});

it('fails closed when production requests the mobile-derived scheme', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(NetbankStandingAddressProfile::class)->scheme())
        ->toThrow(
            NetbankFundingConfigurationException::class,
            'Production requires the netbank-account-hmac-v2',
        );
});

it('requires a dedicated key for the HMAC scheme', function () {
    config()->set(
        'payment-gateway.netbank.funding.standing_address.scheme',
        'netbank-account-hmac-v2',
    );

    expect(fn () => app(NetbankStandingAddressProfile::class)
        ->derive(standingAddressRequest(routingReference: null)))
        ->toThrow(
            NetbankFundingConfigurationException::class,
            'requires a dedicated key and key identifier',
        );
});

/**
 * @return array<string, mixed>
 */
function reusableFundingTransaction(
    string $transactionId = 'transaction-123',
    string $destination = '9150009173011987',
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

function standingAddressRequest(
    FundingAddressPurpose $purpose = FundingAddressPurpose::AccountFunding,
    ?string $routingReference = '09173011987',
    int $derivationCounter = 0,
): StandingFundingAddressRequestData {
    return new StandingFundingAddressRequestData(
        ownerReference: 'App\\Models\\User:5',
        accountReference: 'wallet:01JACCOUNT',
        purpose: $purpose,
        currency: 'PHP',
        routingReference: $routingReference,
        derivationCounter: $derivationCounter,
    );
}

function standingFundingAddressForPurpose(FundingAddressPurpose $purpose): string
{
    $derived = app(NetbankStandingAddressProfile::class)
        ->derive(standingAddressRequest($purpose, routingReference: null));

    return '91500'.$derived->reference;
}

function useHmacStandingAddressScheme(): void
{
    config()->set('payment-gateway.netbank.funding.standing_address', [
        'scheme' => 'netbank-account-hmac-v2',
        'reference_length' => 11,
        'hmac_key_id' => 'v2-test',
        'hmac_key' => str_repeat('hmac-test-key-', 3),
    ]);
}
