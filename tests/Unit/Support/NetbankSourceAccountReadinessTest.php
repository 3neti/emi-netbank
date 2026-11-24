<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderSourceAccountReadiness;
use LBHurtado\EmiCore\Data\Providers\ProviderSourceAccountReadinessRequestData;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Support\NetbankSourceAccountReadiness;

it('exposes source-account readiness through the core capability', function (): void {
    $providers = iterator_to_array(app()->tagged('emi.provider-source-account-readiness'));
    expect($providers)->toHaveCount(1)->and($providers[0])->toBeInstanceOf(ProviderSourceAccountReadiness::class);
});

it('uses available balance and preserves insufficient liquidity', function (): void {
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', true);
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number', 'test-account');
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')->once()->with('test-account')->andReturn([
        'balance' => 500000, 'available_balance' => 100000, 'currency' => 'PHP', 'as_of' => '2026-06-19T10:00:00+08:00', 'raw' => [],
    ]);

    $result = (new NetbankSourceAccountReadiness($gateway))->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(requiredAmountMinor: 150000));
    expect($result['ready'])->toBeFalse()->and($result['available_balance_minor'])->toBe(100000);
});

it('checks the explicitly selected account without falling back to the shared account', function (): void {
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', false);
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number', 'shared-account');
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')->once()->with('personal-account')->andReturn([
        'balance' => 5000, 'available_balance' => 4500, 'currency' => 'PHP', 'as_of' => now()->toIso8601String(), 'raw' => [],
    ]);
    $result = (new NetbankSourceAccountReadiness($gateway))->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(
        requiredAmountMinor: 4000, sourceAccountNumber: 'personal-account',
    ));
    expect($result['ready'])->toBeTrue()->and($result['checked'])->toBeTrue()
        ->and($result['available_balance_minor'])->toBe(4500)->and($result)->not->toHaveKey('account_number');
});

it('fails closed when the selected account number is blank', function (): void {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldNotReceive('checkAccountBalance');
    $result = (new NetbankSourceAccountReadiness($gateway))->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(
        sourceAccountNumber: ' ',
    ));
    expect($result['ready'])->toBeFalse()->and($result['checked'])->toBeFalse();
});

it('rejects stale selected account balance evidence', function (): void {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')->once()->with('personal-account')->andReturn([
        'balance' => 5000, 'available_balance' => 4500, 'currency' => 'PHP',
        'as_of' => now()->subDay()->toIso8601String(), 'raw' => [],
    ]);
    $result = (new NetbankSourceAccountReadiness($gateway))->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(
        requiredAmountMinor: 4000, sourceAccountNumber: 'personal-account',
    ));
    expect($result)->toMatchArray(['ready' => false, 'checked' => true, 'reason' => 'balance_stale']);
});

it('does not substitute total balance for a missing selected account available balance', function (): void {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')->once()->with('personal-account')->andReturn([
        'balance' => 5000, 'currency' => 'PHP', 'as_of' => now()->toIso8601String(), 'raw' => [],
    ]);
    $result = (new NetbankSourceAccountReadiness($gateway))->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(
        requiredAmountMinor: 4000, sourceAccountNumber: 'personal-account',
    ));
    expect($result)->toMatchArray(['ready' => false, 'checked' => true, 'reason' => 'balance_unavailable']);
});

it('accepts a recent authenticated response when the matching account has no bank timestamp', function (): void {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')->once()->with('113-001-00006-8')->andReturn([
        'balance' => 5000, 'available_balance' => 4500, 'currency' => 'PHP',
        'as_of' => null, 'fetched_at' => now()->toIso8601String(),
        'raw' => ['account_number' => '113-001-00006-8', 'available_balance' => ['cur' => 'PHP', 'num' => '4500']],
    ]);
    $result = (new NetbankSourceAccountReadiness($gateway))->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(
        requiredAmountMinor: 4000, sourceAccountNumber: '113-001-00006-8',
    ));
    expect($result)->toMatchArray(['ready' => true, 'checked' => true, 'freshness_source' => 'authenticated_response_fetch_time', 'as_of' => null]);
});

it('does not accept another account or a malformed available balance as freshness evidence', function (array $raw): void {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')->once()->andReturn([
        'balance' => 5000, 'available_balance' => 4500, 'currency' => 'PHP',
        'as_of' => null, 'fetched_at' => now()->toIso8601String(), 'raw' => $raw,
    ]);
    $result = (new NetbankSourceAccountReadiness($gateway))->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(
        requiredAmountMinor: 4000, sourceAccountNumber: '113-001-00006-8',
    ));
    expect($result)->toMatchArray(['ready' => false, 'reason' => 'balance_unavailable']);
})->with([
    [['account_number' => '113-001-00001-9', 'available_balance' => ['cur' => 'PHP', 'num' => '4500']]],
    [['account_number' => '113-001-00006-8', 'available_balance' => ['cur' => 'USD', 'num' => '4500']]],
    [['account_number' => '113-001-00006-8']],
]);

it('rejects an old fetch time even when the account response matches', function (): void {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkAccountBalance')->once()->andReturn([
        'balance' => 5000, 'available_balance' => 4500, 'currency' => 'PHP',
        'as_of' => null, 'fetched_at' => now()->subDay()->toIso8601String(),
        'raw' => ['account_number' => '113-001-00006-8', 'available_balance' => ['cur' => 'PHP', 'num' => '4500']],
    ]);
    $result = (new NetbankSourceAccountReadiness($gateway))->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(
        requiredAmountMinor: 4000, sourceAccountNumber: '113-001-00006-8',
    ));
    expect($result)->toMatchArray(['ready' => false, 'reason' => 'balance_stale']);
});
