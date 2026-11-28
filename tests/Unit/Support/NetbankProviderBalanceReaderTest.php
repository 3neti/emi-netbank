<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\EmiCore\Exceptions\ProviderLivePreflightFailed;
use LBHurtado\PaymentGateway\Exceptions\NetbankBalanceReadException;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Support\NetbankProviderBalanceReader;

it('uses the authoritative NetBank ledger balance without exposing its account', function () {
    config()->set(
        'payment-gateway.netbank.funding.corporate_account_number',
        '9150012345678901',
    );
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')
        ->once()
        ->with('9150012345678901')
        ->andReturn([
            'balance' => 2_100_000_00,
            'available_balance' => 2_000_000_00,
            'currency' => 'PHP',
            'as_of' => '2026-07-24T09:30:00+08:00',
            'raw' => ['balance' => ['cur' => 'PHP']],
        ]);

    $observation = (new NetbankProviderBalanceReader($client))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'NETBANK',
            connectionReference: 'netbank-primary',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            currency: 'PHP',
        ),
    );

    expect($observation->amountMinor)->toBe(2_100_000_00)
        ->and($observation->currency)->toBe('PHP')
        ->and($observation->evidenceReference)->toStartWith('netbank-balance:')
        ->and(json_encode($observation->toArray()))
        ->not->toContain('9150012345678901');
});

it('uses the account configured for a dedicated Treasury connection', function (): void {
    config()->set('payment-gateway.netbank.funding.corporate_account_number', 'shared-account');
    config()->set('payment-gateway.netbank.funding.treasury_connection_accounts', [
        'netbank-dedicated-secondary' => [
            'account_number' => 'dedicated-account',
            'account_name' => 'Dedicated Treasury',
            'vca_alias' => '89628',
        ],
    ]);
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->with('dedicated-account')->andReturn([
        'balance' => 20_901,
        'available_balance' => 20_901,
        'currency' => 'PHP',
        'as_of' => '2026-11-27T06:54:15Z',
        'raw' => ['balance' => ['cur' => 'PHP']],
    ]);

    $observation = (new NetbankProviderBalanceReader($client))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'netbank',
            connectionReference: 'netbank-dedicated-secondary',
            settlementResourceReference: 'resource:netbank:dedicated:secondary',
            currency: 'PHP',
        ),
    );

    expect($observation->amountMinor)->toBe(20_901)
        ->and($observation->connectionReference)->toBe('netbank-dedicated-secondary');
});

it('keeps the primary Treasury connection on its shared-account fallback when dedicated accounts are configured', function (): void {
    config()->set('payment-gateway.netbank.funding.corporate_account_number', 'shared-account');
    config()->set('payment-gateway.netbank.funding.treasury_connection_accounts', [
        'netbank-dedicated-secondary' => [
            'account_number' => 'dedicated-account',
            'account_name' => 'Dedicated Treasury',
            'vca_alias' => '89628',
        ],
    ]);
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->with('shared-account')->andReturn([
        'balance' => 45_779,
        'available_balance' => 45_779,
        'currency' => 'PHP',
        'as_of' => '2026-11-28T11:43:39Z',
        'raw' => ['balance' => ['cur' => 'PHP']],
    ]);

    $observation = (new NetbankProviderBalanceReader($client))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'netbank',
            connectionReference: 'netbank-primary',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            currency: 'PHP',
        ),
    );

    expect($observation->connectionReference)->toBe('netbank-primary');
});

it('refuses to read a dedicated Treasury connection through the shared account', function (): void {
    config()->set('payment-gateway.netbank.funding.corporate_account_number', 'shared-account');
    config()->set('payment-gateway.netbank.funding.treasury_connection_accounts', []);
    $client = Mockery::mock(NetbankFundingApiClient::class);

    expect(fn (): mixed => (new NetbankProviderBalanceReader($client))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'netbank',
            connectionReference: 'netbank-dedicated-secondary',
            settlementResourceReference: 'resource:netbank:dedicated:secondary',
            currency: 'PHP',
        ),
    ))->toThrow(
        RuntimeException::class,
        'netbank-dedicated-secondary] has no configured account',
    );
});

it('rejects an invalid ledger balance even when available liquidity is valid', function () {
    config()->set(
        'payment-gateway.netbank.funding.corporate_account_number',
        '9150012345678901',
    );
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->andReturn([
        'balance' => null,
        'available_balance' => 100_00,
        'currency' => 'PHP',
        'as_of' => '2026-07-24T09:30:00+08:00',
        'raw' => ['balance' => ['cur' => 'PHP']],
    ]);

    expect(fn () => (new NetbankProviderBalanceReader($client))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'netbank',
            connectionReference: 'netbank-primary',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            currency: 'PHP',
        ),
    ))->toThrow(
        ProviderLivePreflightFailed::class,
        'invalid_balance_response',
    );
});

it('rejects a swallowed gateway failure instead of treating it as a zero balance', function () {
    config()->set(
        'payment-gateway.netbank.funding.corporate_account_number',
        '9150012345678901',
    );
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->andReturn([
        'balance' => 0,
        'available_balance' => 0,
        'currency' => 'PHP',
        'as_of' => null,
        'raw' => [],
    ]);

    expect(fn () => (new NetbankProviderBalanceReader($client))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'netbank',
            connectionReference: 'netbank-primary',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            currency: 'PHP',
        ),
    ))->toThrow(
        ProviderLivePreflightFailed::class,
        'invalid_balance_response',
    );
});

it('rejects a provider currency mismatch', function () {
    config()->set(
        'payment-gateway.netbank.funding.corporate_account_number',
        '9150012345678901',
    );
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->andReturn([
        'balance' => 100_00,
        'available_balance' => 100_00,
        'currency' => 'USD',
        'as_of' => '2026-07-24T09:30:00+08:00',
        'raw' => ['balance' => ['cur' => 'USD']],
    ]);

    expect(fn () => (new NetbankProviderBalanceReader($client))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'netbank',
            connectionReference: 'netbank-primary',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            currency: 'PHP',
        ),
    ))->toThrow(
        ProviderLivePreflightFailed::class,
        'invalid_balance_response',
    );
});

it('preserves a sanitized gateway failure code without leaking gateway details', function () {
    config()->set(
        'payment-gateway.netbank.funding.corporate_account_number',
        '9150012345678901',
    );
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->andThrow(
        new NetbankBalanceReadException(
            ProviderLivePreflightFailureCode::DnsResolutionFailed,
        ),
    );

    expect(fn () => (new NetbankProviderBalanceReader($client))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'netbank',
            connectionReference: 'netbank-primary',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            currency: 'PHP',
        ),
    ))->toThrow(ProviderLivePreflightFailed::class, 'dns_resolution_failed');
});

it('records the fetch time when the provider supplies no balance timestamp', function (): void {
    config()->set('payment-gateway.netbank.funding.corporate_account_number', 'test-account');
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->andReturn([
        'balance' => 28800, 'available_balance' => 28800, 'currency' => 'PHP',
        'as_of' => null, 'fetched_at' => '2026-11-22T03:00:00Z',
        'raw' => ['balance' => ['cur' => 'PHP']],
    ]);
    $observation = (new NetbankProviderBalanceReader($client))->readBalance(new ProviderBalanceRequestData(
        provider: 'netbank', connectionReference: 'netbank-primary',
        settlementResourceReference: 'resource:netbank:corporate-vca', currency: 'PHP',
    ));
    expect($observation->observedAt->format(DATE_ATOM))->toBe('2026-11-22T03:00:00+00:00');
});

it('preserves a provider balance timestamp when one is supplied', function (): void {
    config()->set('payment-gateway.netbank.funding.corporate_account_number', 'test-account');
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->andReturn([
        'balance' => 28800, 'available_balance' => 28800, 'currency' => 'PHP',
        'as_of' => '2026-11-06T00:00:00Z', 'fetched_at' => '2026-11-22T03:00:00Z',
        'raw' => ['balance' => ['cur' => 'PHP']],
    ]);
    $observation = (new NetbankProviderBalanceReader($client))->readBalance(new ProviderBalanceRequestData(
        provider: 'netbank', connectionReference: 'netbank-primary',
        settlementResourceReference: 'resource:netbank:corporate-vca', currency: 'PHP',
    ));
    expect($observation->observedAt->format(DATE_ATOM))->toBe('2026-11-06T00:00:00+00:00');
});
