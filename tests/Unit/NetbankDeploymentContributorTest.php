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
            'NETBANK_DISBURSEMENT_ENDPOINT',
            'NETBANK_QR_ENDPOINT',
            'NETBANK_STATUS_ENDPOINT',
            'NETBANK_CLIENT_SECRET',
            'NETBANK_SOURCE_ACCOUNT_NUMBER',
            'NETBANK_FUNDING_CLIENT_ID',
            'NETBANK_FUNDING_CLIENT_SECRET',
            'NETBANK_FUNDING_CORPORATE_ACCOUNT_NUMBER',
            'NETBANK_FUNDING_BALANCE_ENDPOINT',
            'NETBANK_TEST_MODE',
        ])
        ->and($variables['NETBANK_FUNDING_CLIENT_SECRET']->secret)->toBeTrue()
        ->and($variables['NETBANK_FUNDING_CLIENT_SECRET']->safeExample)->toBeNull()
        ->and($variables['NETBANK_FUNDING_CLIENT_SECRET']->requiredForProviders)->toBe([])
        ->and($variables['NETBANK_CLIENT_SECRET']->requiredForProviders)->toBe(['netbank'])
        ->and($variables['NETBANK_QR_ENDPOINT']->requiredForProviders)->toBe(['netbank'])
        ->and($variables['NETBANK_TEST_MODE']->requiredForProviders)->toBe([])
        ->and($connection->reference)->toBe('netbank-primary')
        ->and($connection->requiredCapabilities)->toContain(
            ProviderCapability::BalanceRead,
            ProviderCapability::SettlementExecution,
        );
});
