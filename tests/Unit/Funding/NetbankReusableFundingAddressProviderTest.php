<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

it('returns only sanitized incoming observations for the exact reusable VCA', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [
                reusableFundingTransaction(),
                reusableFundingTransaction(
                    transactionId: 'wrong-destination',
                    destination: '915009999999999999999',
                ),
                reusableFundingTransaction(
                    transactionId: 'outgoing',
                    type: 'Debit',
                ),
            ],
        ]),
    ]);

    $observations = app(NetbankReusableFundingAddressProvider::class)
        ->observations('915001234567890123456');

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
        '/v1/vca/915001234567890123456/transactions?',
    ) && $request->data()['account_number'] === '113001000019');
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
