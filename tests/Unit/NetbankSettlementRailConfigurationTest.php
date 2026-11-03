<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderSettlementRailConfiguration;
use LBHurtado\EmiCore\Enums\SettlementRail;

it('registers configured settlement rails as a provider capability', function (): void {
    config()->set('omnipay.gateways.netbank.options.rails.INSTAPAY', [
        'enabled' => true,
        'max_amount' => 5000000,
        'fee' => 1000,
    ]);

    $capability = collect(app()->tagged('emi.provider-settlement-rail-configurations'))
        ->first(fn ($candidate) => $candidate instanceof ProviderSettlementRailConfiguration && $candidate->providerCode() === 'netbank');

    expect($capability)->not->toBeNull()
        ->and($capability->configurationFor(SettlementRail::INSTAPAY))->toMatchArray([
            'enabled' => true,
            'max_amount' => 5000000,
            'fee' => 1000,
        ]);
});
