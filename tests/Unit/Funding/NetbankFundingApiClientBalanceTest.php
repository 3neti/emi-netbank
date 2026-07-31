<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\PaymentGateway\Exceptions\NetbankBalanceReadException;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;

beforeEach(function () {
    Cache::clear();
    Http::preventStrayRequests();
    config()->set('payment-gateway.netbank.funding', [
        'api_url' => 'https://api.netbank.test',
        'token_url' => 'https://auth.netbank.test/oauth2/token',
        'client_id' => 'sensitive-client-id',
        'client_secret' => 'sensitive-client-secret',
        'corporate_account_number' => '9150012345678901',
        'balance_endpoint' => '/v1/accounts',
        'connect_timeout_seconds' => 5,
        'timeout_seconds' => 15,
    ]);
});

dataset('netbank connection failures', [
    'dns' => [
        'cURL error 6: Could not resolve host: sensitive.example',
        ProviderLivePreflightFailureCode::DnsResolutionFailed,
    ],
    'timeout' => [
        'cURL error 28: Operation timed out',
        ProviderLivePreflightFailureCode::ConnectionTimeout,
    ],
    'tls' => [
        'cURL error 60: SSL certificate problem',
        ProviderLivePreflightFailureCode::TlsFailure,
    ],
]);

it('reads and normalizes authoritative ledger and available balances', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'sensitive-access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/accounts/9150012345678901' => Http::response([
            'account_number' => '9150012345678901',
            'balance' => ['cur' => 'PHP', 'num' => '50000'],
            'available_balance' => ['cur' => 'PHP', 'num' => '45000'],
            'created_date' => '2026-07-29T09:30:00+08:00',
        ]),
    ]);

    $balance = app(NetbankFundingApiClient::class)
        ->balance('9150012345678901');

    expect($balance)->toMatchArray([
        'balance' => 50_000,
        'available_balance' => 45_000,
        'currency' => 'PHP',
        'as_of' => '2026-07-29T09:30:00+08:00',
    ]);
});

it('classifies an authentication rejection without retaining provider secrets', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'message' => 'sensitive provider authentication body',
        ], 401),
    ]);

    try {
        app(NetbankFundingApiClient::class)->balance('9150012345678901');
        $this->fail('Expected the balance read to fail.');
    } catch (NetbankBalanceReadException $exception) {
        expect($exception->failureCode)
            ->toBe(ProviderLivePreflightFailureCode::AuthenticationFailed)
            ->and($exception->getMessage())->toBe(
                'NetBank balance read failed [authentication_failed].',
            )
            ->not->toContain('sensitive')
            ->not->toContain('9150012345678901')
            ->not->toContain('https://');
    }
});

it('classifies DNS, timeout, and TLS connection failures', function (
    string $message,
    ProviderLivePreflightFailureCode $expected,
) {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'sensitive-access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/*' => Http::failedConnection($message),
    ]);

    try {
        app(NetbankFundingApiClient::class)->balance('9150012345678901');
        $this->fail('Expected the balance read to fail.');
    } catch (NetbankBalanceReadException $exception) {
        expect($exception->failureCode)->toBe($expected);
    }
})->with('netbank connection failures');

it('classifies a malformed balance body', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'sensitive-access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/accounts/9150012345678901' => Http::response([
            'account_number' => '9150012345678901',
            'balance' => ['cur' => 'PHP'],
        ]),
    ]);

    try {
        app(NetbankFundingApiClient::class)->balance('9150012345678901');
        $this->fail('Expected the balance read to fail.');
    } catch (NetbankBalanceReadException $exception) {
        expect($exception->failureCode)
            ->toBe(ProviderLivePreflightFailureCode::InvalidBalanceResponse);
    }
});
