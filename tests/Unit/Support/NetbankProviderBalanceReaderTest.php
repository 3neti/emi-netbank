<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\EmiCore\Exceptions\ProviderLivePreflightFailed;
use LBHurtado\PaymentGateway\Exceptions\NetbankBalanceReadException;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Support\NetbankProviderBalanceReader;

it('normalizes an authoritative NetBank available balance without exposing its account', function () {
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

    expect($observation->amountMinor)->toBe(2_000_000_00)
        ->and($observation->currency)->toBe('PHP')
        ->and($observation->evidenceReference)->toStartWith('netbank-balance:')
        ->and(json_encode($observation->toArray()))
        ->not->toContain('9150012345678901');
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
