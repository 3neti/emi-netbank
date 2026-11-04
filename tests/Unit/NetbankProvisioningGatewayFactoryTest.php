<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderProvisioningGatewayFactory;
use LBHurtado\EmiCore\Contracts\ProviderSourceAccountReadiness;
use LBHurtado\EmiCore\Data\Providers\ProviderSourceAccountReadinessRequestData;

it('provides bank-account provisioning through the core capability', function (): void {
    $factory = collect(app()->tagged('emi.provider-provisioning-gateway-factories'))
        ->first(fn ($candidate) => $candidate instanceof ProviderProvisioningGatewayFactory && $candidate->providerCode() === 'netbank');

    expect($factory)->not->toBeNull()
        ->and($factory->requiresLocalWallet('ledger_wallet'))->toBeTrue()
        ->and($factory->requiresLocalWallet('bank_account_link'))->toBeFalse();

    $result = $factory->make()->provision(new stdClass, [
        'mode' => 'bank_account_link', 'bank_code' => 'GXCHPHM2XXX', 'account_number_masked' => '*******1987',
    ]);
    expect($result)->toMatchArray([
        'provider' => 'netbank', 'status' => 'ready', 'provider_bank_account_id' => 'NETBANK-GXCHPHM2XXX-XXXXXXX1987',
    ]);
});

it('uses an explicitly supplied readiness provider without resolving other registrations', function (): void {
    app()->bind('test.unavailable-source', fn () => throw new RuntimeException('unexpected source resolution'));
    app()->tag('test.unavailable-source', 'emi.provider-source-account-readiness');
    $source = new class implements ProviderSourceAccountReadiness
    {
        public function providerCode(): string
        {
            return 'netbank';
        }

        public function inspectSourceAccount(ProviderSourceAccountReadinessRequestData $request): ?array
        {
            return ['ready' => true, 'checked' => true];
        }
    };
    $factory = collect(app()->tagged('emi.provider-provisioning-gateway-factories'))
        ->first(fn ($candidate) => $candidate instanceof ProviderProvisioningGatewayFactory);

    $result = $factory->make($source)->provision(new stdClass, ['mode' => 'bank_account_link']);
    expect(data_get($result, 'metadata.source_account.ready'))->toBeTrue();
});
