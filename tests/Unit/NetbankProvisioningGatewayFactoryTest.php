<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderProvisioningGatewayFactory;

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
