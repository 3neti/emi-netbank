<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\PaymentGateway\Configuration\NetbankDeploymentContributor;

it('contributes sanitized NetBank deployment requirements', function (): void {
    $contributor = new NetbankDeploymentContributor;
    $variables = collect($contributor->environmentVariables())->keyBy('key');
    $connection = $contributor->connectionTemplates()[0];

    expect($contributor->providerCode())->toBe('netbank')
        ->and($variables)->toHaveKeys([
            'NETBANK_FUNDING_CLIENT_ID',
            'NETBANK_FUNDING_CLIENT_SECRET',
            'NETBANK_FUNDING_CORPORATE_ACCOUNT_NUMBER',
            'NETBANK_FUNDING_BALANCE_ENDPOINT',
        ])
        ->and($variables['NETBANK_FUNDING_CLIENT_SECRET']->secret)->toBeTrue()
        ->and($variables['NETBANK_FUNDING_CLIENT_SECRET']->safeExample)->toBeNull()
        ->and($connection->reference)->toBe('netbank-primary')
        ->and($connection->requiredCapabilities)->toContain(ProviderCapability::BalanceRead);
});
