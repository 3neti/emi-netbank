<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Data\Funding\WebhookAuthenticationData;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingNotVerified;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-07-23 09:00:00 Asia/Manila');
    Cache::clear();
    Http::preventStrayRequests();

    config()->set('payment-gateway.netbank.funding', [
        'api_url' => 'https://api.netbank.test',
        'token_url' => 'https://auth.netbank.test/oauth2/token',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'corporate_account_number' => '113001000019',
        'corporate_account_name' => 'X Change Treasury',
        'vca_alias' => '91500',
        'vca_alias_token' => 'configured-alias-token',
        'reference_key' => 'funding-reference-key',
        'pre_transaction_validation_enabled' => true,
        'exact_limits_enabled' => true,
        'timeout_seconds' => 5,
        'verification_lookback_days' => 7,
        'webhook' => [
            'allowed_ips' => ['3.0.84.126', '52.74.254.158'],
            'expected_content_type' => 'text/plain;charset=ISO-8859-1',
        ],
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('registers as a provider-neutral funding adapter', function () {
    $adapter = app(NetbankFundingProviderAdapter::class);

    expect($adapter)->toBeInstanceOf(FundingProviderAdapter::class)
        ->and($adapter->providerCode())->toBe('netbank')
        ->and(collect(app()->tagged('emi.funding-provider-adapters'))->contains($adapter))->toBeTrue();
});

it('creates deterministic exact one-time VCA funding instructions', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/vca/pre-transaction/register' => Http::response([], 204),
        'https://api.netbank.test/v1/vca/create' => Http::response([], 201),
    ]);

    $request = new FundingInstructionRequestData(
        provider: 'netbank',
        fundingReference: 'FND-01K123456789',
        amountMinor: 25_000,
        currency: 'php',
        accountReference: 'account-123',
        expiresAt: new DateTimeImmutable('2026-07-23T10:00:00+08:00'),
    );

    $instructions = app(NetbankFundingProviderAdapter::class)->createFundingInstructions($request);

    expect($instructions->provider)->toBe('netbank')
        ->and($instructions->providerReference)->toMatch('/\A91500\d{16}\z/')
        ->and($instructions->fundingAddress)->toBe($instructions->providerReference)
        ->and($instructions->amountMinor)->toBe(25_000)
        ->and($instructions->currency)->toBe('PHP')
        ->and($instructions->displayData)->toMatchArray([
            'institution' => 'NetBank',
            'account_name' => 'X Change Treasury',
            'destination_account' => $instructions->providerReference,
            'amount_minor' => 25_000,
            'currency' => 'PHP',
            'one_time' => true,
            'delivery' => 'manual-bank-or-wallet-transfer',
        ]);

    Http::assertSent(fn (Request $httpRequest): bool => $httpRequest->url() === 'https://auth.netbank.test/oauth2/token'
        && $httpRequest->method() === 'POST'
        && $httpRequest['grant_type'] === 'client_credentials'
        && $httpRequest->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret')));

    Http::assertSent(fn (Request $httpRequest): bool => $httpRequest->url() === 'https://api.netbank.test/v1/vca/pre-transaction/register'
        && $httpRequest->data() === [
            'vca_alias_token' => 'configured-alias-token',
            'vca_reference_number' => substr((string) $instructions->providerReference, 5),
        ]);

    Http::assertSent(fn (Request $httpRequest): bool => $httpRequest->url() === 'https://api.netbank.test/v1/vca/create'
        && $httpRequest->data() === [
            'vca_number' => $instructions->providerReference,
            'account_number' => '113001000019',
            'limits' => [
                'is_one_time_usage' => true,
                'maximum_amount' => ['cur' => 'PHP', 'num' => '25000'],
                'minimum_amount' => ['cur' => 'PHP', 'num' => '25000'],
                'valid_from' => '2026-07-23T01:00:00',
                'valid_to' => '2026-07-23T02:00:00',
            ],
        ]);

    Http::fake([
        'https://api.netbank.test/v1/vca/pre-transaction/register' => Http::response([], 204),
        'https://api.netbank.test/v1/vca/create' => Http::response([], 201),
    ]);

    $reissued = app(NetbankFundingProviderAdapter::class)->createFundingInstructions($request);

    expect($reissued->providerReference)->toBe($instructions->providerReference);
});

it('treats an authenticated webhook as a wake-up hint without trusting its body', function () {
    $adapter = app(NetbankFundingProviderAdapter::class);
    $request = webhookRequest(
        sourceIp: '52.74.254.158',
        contentType: 'text/plain;charset=ISO-8859-1',
        body: 'undocumented provider body with transaction_id=untrusted',
    );

    $authentication = $adapter->authenticateWebhook($request);
    $hint = $adapter->normalizeWebhook(ProviderWebhookReceiptData::fromRequest($request, $authentication));

    expect($authentication->authenticated)->toBeTrue()
        ->and($authentication->method)->toBe('source-ip-allowlist')
        ->and($hint->eventType)->toBe('netbank.credit-notification')
        ->and($hint->providerEventId)->toBeNull()
        ->and($hint->requestId)->toBeNull();
});

it('rejects webhook requests outside the transport contract', function (?string $sourceIp, ?string $contentType, string $reason) {
    $authentication = app(NetbankFundingProviderAdapter::class)->authenticateWebhook(
        webhookRequest($sourceIp, $contentType),
    );

    expect($authentication)->toEqual(new WebhookAuthenticationData(
        authenticated: false,
        method: 'source-ip-allowlist',
        reason: $reason,
    ));
})->with([
    'unknown source' => ['203.0.113.10', 'text/plain;charset=ISO-8859-1', 'source-ip-not-allowed'],
    'unexpected media type' => ['3.0.84.126', 'application/json', 'unexpected-content-type'],
    'missing source' => [null, 'text/plain', 'source-ip-not-allowed'],
]);

it('verifies a settled incoming credit from authoritative VCA history', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [netbankTransaction()],
        ]),
    ]);

    $observation = app(NetbankFundingProviderAdapter::class)->verifyFunding(verification());

    expect($observation->provider)->toBe('netbank')
        ->and($observation->providerTransactionId)->toBe('transaction-123')
        ->and($observation->providerOperationId)->toBe('operation-456')
        ->and($observation->requestId)->toBe('reference-789')
        ->and($observation->grossAmountMinor)->toBe(25_000)
        ->and($observation->feeAmountMinor)->toBe(50)
        ->and($observation->netAmountMinor)->toBe(24_950)
        ->and($observation->currency)->toBe('PHP')
        ->and($observation->providerStatus)->toBe('settled')
        ->and($observation->verificationSource)->toBe('netbank-vca-transaction-history')
        ->and($observation->fundingAddress)->toBe('sha256:'.hash('sha256', '915001234567890123456'))
        ->and($observation->providerAccountReference)->toBe('sha256:'.hash('sha256', '113001000019'))
        ->and($observation->settledAt?->format(DATE_ATOM))->toBe('2026-07-23T01:06:00+00:00')
        ->and($observation->webhookReceiptId)->toBe(42)
        ->and($observation->metadata)->toBe([
            'description' => 'EXTERNAL_TRANSFER_INCOMING',
            'type' => 'Credit',
            'settlement_rail' => 'INSTAPAY',
            'destination_verified' => true,
            'expected_amount_matches' => true,
            'expected_currency_matches' => true,
        ])
        ->and($observation->metadata)->not->toHaveKeys(['sender', 'account_number']);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.netbank.test/v1/vca/915001234567890123456/transactions?')
        && $request->method() === 'GET'
        && $request->data()['account_number'] === '113001000019'
        && $request->data()['limit'] === 100);
});

it('preserves a mismatched observation for suspense instead of claiming it matches', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [netbankTransaction(amountMinor: 24_000, currency: 'USD')],
        ]),
    ]);

    $observation = app(NetbankFundingProviderAdapter::class)->verifyFunding(verification());

    expect($observation->grossAmountMinor)->toBe(24_000)
        ->and($observation->currency)->toBe('USD')
        ->and($observation->metadata['expected_amount_matches'])->toBeFalse()
        ->and($observation->metadata['expected_currency_matches'])->toBeFalse();
});

it('does not mark a pending provider observation as settled', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [netbankTransaction(status: 'Pending', statusDetails: [])],
        ]),
    ]);

    $observation = app(NetbankFundingProviderAdapter::class)->verifyFunding(verification());

    expect($observation->providerStatus)->toBe('pending')
        ->and($observation->settledAt)->toBeNull();
});

it('fails closed when an incoming credit cannot be selected safely', function (array $transactions, string $message) {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response(['transactions' => $transactions]),
    ]);

    expect(fn () => app(NetbankFundingProviderAdapter::class)->verifyFunding(verification()))
        ->toThrow(NetbankFundingNotVerified::class, $message);
})->with([
    'no credit' => [[], 'did not return an incoming VCA transaction'],
    'multiple exact credits' => [
        [netbankTransaction(), netbankTransaction(transactionId: 'transaction-456')],
        'multiple VCA transactions',
    ],
]);

it('does not disclose provider response bodies when an API request fails', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'secret' => 'must-not-leak',
        ], 401),
    ]);

    try {
        app(NetbankFundingProviderAdapter::class)->verifyFunding(verification());
    } catch (NetbankFundingRequestFailed $exception) {
        expect($exception->getMessage())->toContain('oauth-token')
            ->not->toContain('must-not-leak')
            ->not->toContain('client-secret');

        return;
    }

    $this->fail('Expected a sanitized provider request failure.');
});

function webhookRequest(?string $sourceIp, ?string $contentType, string $body = 'opaque'): ProviderWebhookRequestData
{
    return new ProviderWebhookRequestData(
        provider: 'netbank',
        rawBody: $body,
        contentType: $contentType,
        headers: [],
        sourceIp: $sourceIp,
        receivedAt: new DateTimeImmutable('2026-07-23T01:00:00+00:00'),
    );
}

function verification(): FundingVerificationData
{
    return new FundingVerificationData(
        provider: 'netbank',
        fundingIntentReference: 'FND-01K123456789',
        expectedAmountMinor: 25_000,
        currency: 'PHP',
        fundingAddress: '915001234567890123456',
        webhookReceiptId: 42,
    );
}

/**
 * @param  array<int, array<string, string>>|null  $statusDetails
 * @return array<string, mixed>
 */
function netbankTransaction(
    int $amountMinor = 25_000,
    string $currency = 'PHP',
    string $status = 'Settled',
    ?array $statusDetails = null,
    string $transactionId = 'transaction-123',
): array {
    return [
        'amount' => ['cur' => $currency, 'num' => (string) $amountMinor],
        'date' => '2026-07-23T01:05:00.000Z',
        'description' => 'EXTERNAL_TRANSFER_INCOMING',
        'destination_account' => [
            'account_alias' => '915001234567890123456',
            'account_number' => 'sensitive-destination-account',
        ],
        'fees' => [['amount' => ['cur' => $currency, 'num' => '50']]],
        'operation_id' => 'operation-456',
        'reference_id' => 'reference-789',
        'sender' => [
            'name' => 'Sensitive Sender',
            'account_number' => 'sensitive-sender-account',
        ],
        'settlement_rail' => 'INSTAPAY',
        'status' => $status,
        'status_details' => $statusDetails ?? [[
            'status' => 'Settled',
            'message' => 'SETTLED',
            'updated' => '2026-07-23T01:06:00.000Z',
        ]],
        'transaction_id' => $transactionId,
        'type' => 'Credit',
        'updated' => '2026-07-23T01:06:00.000Z',
    ];
}
