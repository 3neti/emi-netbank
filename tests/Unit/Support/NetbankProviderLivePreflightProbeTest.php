<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\Providers\ProviderLivePreflightRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderLivePreflightResultData;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\PaymentGateway\Exceptions\NetbankBalanceReadException;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Support\NetbankProviderBalanceReader;
use LBHurtado\PaymentGateway\Support\NetbankProviderLivePreflightProbe;

beforeEach(function () {
    config()->set(
        'payment-gateway.netbank.funding.corporate_account_number',
        '9150012345678901',
    );
});

it('returns an authoritative observation when NetBank is live-ready', function () {
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->andReturn([
        'balance' => 500_00,
        'available_balance' => 500_00,
        'currency' => 'PHP',
        'as_of' => '2026-07-29T09:30:00+08:00',
        'raw' => ['balance' => ['cur' => 'PHP']],
    ]);

    $result = livePreflightResult($client);

    expect($result->ready)->toBeTrue()
        ->and($result->observation?->amountMinor)->toBe(500_00)
        ->and($result->failureCode)->toBeNull()
        ->and(json_encode($result->toArray()))
        ->not->toContain('9150012345678901');
});

it('returns only the sanitized failure code when NetBank rejects authentication', function () {
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->andThrow(
        new NetbankBalanceReadException(
            ProviderLivePreflightFailureCode::AuthenticationFailed,
        ),
    );

    $result = livePreflightResult($client);
    $serialized = json_encode($result->toArray());

    expect($result->ready)->toBeFalse()
        ->and($result->failureCode)
        ->toBe(ProviderLivePreflightFailureCode::AuthenticationFailed)
        ->and($serialized)->toContain('authentication_failed')
        ->not->toContain('9150012345678901')
        ->not->toContain('secret')
        ->not->toContain('https://');
});

it('classifies an invalid NetBank balance payload', function () {
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('balance')->once()->andReturn([
        'balance' => 0,
        'available_balance' => 0,
        'currency' => 'PHP',
        'as_of' => null,
        'raw' => [],
    ]);

    expect(livePreflightResult($client)->failureCode)
        ->toBe(ProviderLivePreflightFailureCode::InvalidBalanceResponse);
});

function livePreflightResult(
    NetbankFundingApiClient $client,
): ProviderLivePreflightResultData {
    return (new NetbankProviderLivePreflightProbe(
        new NetbankProviderBalanceReader($client),
    ))->checkLiveReadiness(
        new ProviderLivePreflightRequestData(
            provider: 'netbank',
            connectionReference: 'netbank-primary',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            currency: 'PHP',
        ),
    );
}
