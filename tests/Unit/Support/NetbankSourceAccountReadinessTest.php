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
