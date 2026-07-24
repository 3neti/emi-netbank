<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Data\Funding\WebhookAuthenticationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingAmbiguous;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingConfigurationException;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingNotVerified;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
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
        'reference_key' => 'funding-reference-key',
        'qr_endpoint' => 'https://api.netbank.test/v1/qrph/generate',
        'qr_merchant_name' => 'X Change',
        'qr_merchant_city' => 'Manila',
        'qr_resolution' => 480,
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

it('provides known-good NetBank QR defaults', function () {
    $repository = Env::getRepository();
    $keys = [
        'NETBANK_FUNDING_QR_ENDPOINT',
        'NETBANK_QR_ENDPOINT',
        'NETBANK_FUNDING_QR_MERCHANT_NAME',
        'NETBANK_FUNDING_QR_MERCHANT_CITY',
    ];
    $original = collect($keys)->mapWithKeys(
        fn (string $key): array => [$key => $repository->get($key)],
    );

    foreach ($keys as $key) {
        $repository->clear($key);
    }

    try {
        $packageConfig = require dirname(__DIR__, 3).'/config/payment-gateway.php';

        expect(data_get($packageConfig, 'netbank.funding.qr_endpoint'))
            ->toBe('https://api.netbank.ph/v1/qrph/generate')
            ->and(data_get($packageConfig, 'netbank.funding.qr_merchant_name'))
            ->toBe('X Change')
            ->and(data_get($packageConfig, 'netbank.funding.qr_merchant_city'))
            ->toBe('Manila');
    } finally {
        foreach ($original as $key => $value) {
            if (is_string($value)) {
                $repository->set($key, $value);
            }
        }
    }
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
        'https://api.netbank.test/v1/vca/pre-transaction/token' => Http::response([
            'vca_alias_token' => 'intent-registration-token',
        ]),
        'https://api.netbank.test/v1/vca/pre-transaction/register' => Http::response([], 204),
        'https://api.netbank.test/v1/vca/create' => Http::response([], 201),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => validPngBase64(),
        ]),
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
        ->and($instructions->qrCode?->mimeType)->toBe('image/png')
        ->and($instructions->qrCode?->base64Payload)->toBe(validPngBase64())
        ->and($instructions->qrCode?->qrMode)->toBe('dynamic')
        ->and($instructions->qrCode?->transactionType)->toBe('p2m')
        ->and($instructions->qrCode?->embeddedAmount)->toBeTrue()
        ->and($instructions->qrCode?->providerGenerated)->toBeTrue()
        ->and($instructions->displayData)->toMatchArray([
            'institution' => 'NetBank',
            'account_name' => 'X Change Treasury',
            'destination_account' => $instructions->providerReference,
            'amount_minor' => 25_000,
            'currency' => 'PHP',
            'one_time' => true,
            'delivery' => 'scan-to-pay',
        ]);

    Http::assertSent(fn (Request $httpRequest): bool => $httpRequest->url() === 'https://auth.netbank.test/oauth2/token'
        && $httpRequest->method() === 'POST'
        && $httpRequest['grant_type'] === 'client_credentials'
        && $httpRequest->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret')));

    Http::assertSent(fn (Request $httpRequest): bool => $httpRequest->url() === 'https://api.netbank.test/v1/vca/pre-transaction/token'
        && $httpRequest->data() === [
            'vca_alias' => '91500',
            'account_number' => '113001000019',
        ]);

    Http::assertSent(fn (Request $httpRequest): bool => $httpRequest->url() === 'https://api.netbank.test/v1/vca/pre-transaction/register'
        && $httpRequest->data() === [
            'vca_alias_token' => 'intent-registration-token',
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

    Http::assertSent(fn (Request $httpRequest): bool => $httpRequest->url() === 'https://api.netbank.test/v1/qrph/generate'
        && $httpRequest->data() === [
            'merchant_name' => 'X Change',
            'merchant_city' => 'Manila',
            'qr_type' => 'Dynamic',
            'qr_transaction_type' => 'P2M',
            'destination_account' => $instructions->providerReference,
            'resolution' => 480,
            'amount' => ['cur' => 'PHP', 'num' => '25000'],
        ]);

    $providerRequestOrder = collect(Http::recorded())
        ->map(fn (array $record): string => $record[0]->url())
        ->filter(fn (string $url): bool => str_starts_with($url, 'https://api.netbank.test/'))
        ->values()
        ->all();

    expect($providerRequestOrder)->toBe([
        'https://api.netbank.test/v1/vca/pre-transaction/token',
        'https://api.netbank.test/v1/vca/pre-transaction/register',
        'https://api.netbank.test/v1/vca/create',
        'https://api.netbank.test/v1/qrph/generate',
    ]);

    Http::fake([
        'https://api.netbank.test/v1/vca/pre-transaction/token' => Http::response([
            'vca_alias_token' => 'retry-registration-token',
        ]),
        'https://api.netbank.test/v1/vca/pre-transaction/register' => Http::response([], 204),
        'https://api.netbank.test/v1/vca/create' => Http::response([], 201),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => validPngBase64(),
        ]),
    ]);

    $reissued = app(NetbankFundingProviderAdapter::class)->createFundingInstructions($request);

    expect($reissued->providerReference)->toBe($instructions->providerReference);
});

it('uses a dedicated destination without reading shared routing values', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/vca/pre-transaction/token' => Http::response([
            'vca_alias_token' => 'dedicated-registration-token',
        ]),
        'https://api.netbank.test/v1/vca/pre-transaction/register' => Http::response([], 204),
        'https://api.netbank.test/v1/vca/create' => Http::response([], 201),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => validPngBase64(),
        ]),
    ]);

    $request = new FundingInstructionRequestData(
        provider: 'netbank',
        fundingReference: 'FND-DEDICATED-01',
        amountMinor: 50_000,
        currency: 'PHP',
        accountReference: 'wallet:dedicated',
        destination: dedicatedDestination(),
    );

    $instructions = app(NetbankFundingProviderAdapter::class)->createFundingInstructions($request);

    expect($instructions->providerReference)->toStartWith('54321')
        ->and($instructions->displayData['account_name'])->toBe('Dedicated Treasury');

    Http::assertSent(fn (Request $httpRequest): bool => $httpRequest->url() === 'https://api.netbank.test/v1/vca/pre-transaction/register'
        && $httpRequest->data()['vca_alias_token'] === 'dedicated-registration-token');

    Http::assertSent(fn (Request $httpRequest): bool => $httpRequest->url() === 'https://api.netbank.test/v1/vca/create'
        && $httpRequest->data()['account_number'] === '991100001234');
});

it('generates a VCA alias token for an explicit account and alias', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/vca/pre-transaction/token' => Http::response([
            'vca_alias_token' => 'generated-write-only-token',
        ]),
    ]);

    $token = app(NetbankFundingApiClient::class)
        ->generateAliasToken('991100001234', '54321');

    expect($token)->toBe('generated-write-only-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.netbank.test/v1/vca/pre-transaction/token'
        && $request->data() === [
            'vca_alias' => '54321',
            'account_number' => '991100001234',
        ]);
});

it('stops before registration when NetBank token generation fails', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/vca/pre-transaction/token' => Http::response([
            'message' => 'sensitive-provider-body',
        ], 500),
    ]);

    expect(fn () => app(NetbankFundingProviderAdapter::class)->createFundingInstructions(
        new FundingInstructionRequestData(
            provider: 'netbank',
            fundingReference: 'FND-TOKEN-FAILURE',
            amountMinor: 25_000,
            currency: 'PHP',
            accountReference: 'account-123',
        ),
    ))->toThrow(
        NetbankFundingRequestFailed::class,
        'generate-vca-alias-token',
    );

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains(
        $request->url(),
        '/pre-transaction/register',
    ));
});

it('fails safely when NetBank does not return a valid base64 png', function (mixed $qrCode) {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/vca/pre-transaction/token' => Http::response([
            'vca_alias_token' => 'qr-failure-registration-token',
        ]),
        'https://api.netbank.test/v1/vca/pre-transaction/register' => Http::response([], 204),
        'https://api.netbank.test/v1/vca/create' => Http::response([], 201),
        'https://api.netbank.test/v1/qrph/generate' => Http::response([
            'qr_code' => $qrCode,
            'secret' => 'must-not-leak',
        ]),
    ]);

    try {
        app(NetbankFundingProviderAdapter::class)->createFundingInstructions(new FundingInstructionRequestData(
            provider: 'netbank',
            fundingReference: 'FND-QR-FAILURE',
            amountMinor: 25_000,
            currency: 'PHP',
            accountReference: 'account-123',
        ));
    } catch (NetbankFundingRequestFailed $exception) {
        expect($exception->getMessage())->toContain('generate-qrph')
            ->not->toContain('must-not-leak')
            ->not->toContain('113001000019');

        return;
    }

    $this->fail('Expected invalid NetBank QR data to fail closed.');
})->with([
    'missing' => null,
    'not base64' => 'not-a-base64-png',
    'base64 non png' => base64_encode('not a png'),
]);

it('reuses the deterministic VCA when QR issuance is retried', function () {
    $qrAttempts = 0;

    Http::fake(function (Request $request) use (&$qrAttempts) {
        if ($request->url() === 'https://auth.netbank.test/oauth2/token') {
            return Http::response(['access_token' => 'access-token']);
        }

        if ($request->url() === 'https://api.netbank.test/v1/vca/pre-transaction/token') {
            return Http::response([
                'vca_alias_token' => 'retry-registration-token-'.$qrAttempts,
            ]);
        }

        if ($request->url() === 'https://api.netbank.test/v1/qrph/generate') {
            $qrAttempts++;

            return $qrAttempts === 1
                ? Http::response(['qr_code' => 'invalid'])
                : Http::response(['qr_code' => validPngBase64()]);
        }

        return Http::response([], 204);
    });

    $request = new FundingInstructionRequestData(
        provider: 'netbank',
        fundingReference: 'FND-QR-RETRY',
        amountMinor: 25_000,
        currency: 'PHP',
        accountReference: 'account-123',
    );

    expect(fn () => app(NetbankFundingProviderAdapter::class)->createFundingInstructions($request))
        ->toThrow(NetbankFundingRequestFailed::class);

    $instructions = app(NetbankFundingProviderAdapter::class)->createFundingInstructions($request);
    $destinations = collect(Http::recorded())
        ->filter(fn (array $record): bool => $record[0]->url() === 'https://api.netbank.test/v1/qrph/generate')
        ->map(fn (array $record): string => (string) $record[0]['destination_account'])
        ->values()
        ->all();

    expect($qrAttempts)->toBe(2)
        ->and($destinations)->toHaveCount(2)
        ->and(array_unique($destinations))->toHaveCount(1)
        ->and($instructions->providerReference)->toBe($destinations[0]);
});

it('requires every NetBank QR funding readiness field', function (string $key) {
    config()->set("payment-gateway.netbank.funding.{$key}", null);

    expect(fn () => app(NetbankFundingProviderAdapter::class)->createFundingInstructions(new FundingInstructionRequestData(
        provider: 'netbank',
        fundingReference: 'FND-READINESS',
        amountMinor: 25_000,
        currency: 'PHP',
        accountReference: 'account-123',
    )))->toThrow(NetbankFundingConfigurationException::class);
})->with([
    'api_url',
    'token_url',
    'client_id',
    'client_secret',
    'corporate_account_number',
    'corporate_account_name',
    'vca_alias',
    'reference_key',
    'qr_endpoint',
    'qr_merchant_name',
    'qr_merchant_city',
]);

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

it('verifies dedicated funding against the snapshotted corporate account', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response([
            'transactions' => [netbankTransaction()],
        ]),
    ]);

    $verification = verification();
    $verification->destination = dedicatedDestination();
    $observation = app(NetbankFundingProviderAdapter::class)->verifyFunding($verification);

    expect($observation->providerAccountReference)
        ->toBe('sha256:'.hash('sha256', '991100001234'));

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/transactions?')
        && $request->data()['account_number'] === '991100001234');
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

it('fails closed when an incoming credit cannot be selected safely', function (
    array $transactions,
    string $exception,
    string $message,
) {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response(['access_token' => 'access-token']),
        'https://api.netbank.test/v1/vca/*/transactions*' => Http::response(['transactions' => $transactions]),
    ]);

    expect(fn () => app(NetbankFundingProviderAdapter::class)->verifyFunding(verification()))
        ->toThrow($exception, $message);
})->with([
    'no credit' => [
        [],
        NetbankFundingNotVerified::class,
        'did not return an incoming VCA transaction',
    ],
    'multiple exact credits' => [
        [netbankTransaction(), netbankTransaction(transactionId: 'transaction-456')],
        NetbankFundingAmbiguous::class,
        'multiple VCA transactions',
    ],
]);

it('maps absent and ambiguous NetBank evidence to provider-neutral outcomes', function () {
    expect(new NetbankFundingNotVerified('absent'))->toBeInstanceOf(ProviderFundingNotObserved::class)
        ->and(new NetbankFundingAmbiguous('ambiguous'))->toBeInstanceOf(ProviderFundingVerificationIndeterminate::class);
});

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

function dedicatedDestination(): FundingDestinationData
{
    return new FundingDestinationData(
        provider: 'netbank',
        mode: 'dedicated',
        destinationType: 'bank_account',
        accountReference: 'wallet:dedicated',
        displayReference: '•••• 1234 · VCA 54321',
        fingerprint: hash('sha256', 'netbank|991100001234|54321'),
        verificationStatus: 'verified',
        bankAccountNumber: '991100001234',
        bankAccountName: 'Dedicated Treasury',
        routingAlias: '54321',
        routingCredential: 'dedicated-alias-token',
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

function validPngBase64(): string
{
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==';
}
