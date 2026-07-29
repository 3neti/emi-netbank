<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\FundingInstructionIssuer;
use LBHurtado\EmiCore\Contracts\ProviderBalanceReader;
use LBHurtado\EmiCore\Contracts\ProviderFundingEvidenceVerifier;
use LBHurtado\EmiCore\Contracts\ProviderLivePreflightProbe;
use LBHurtado\EmiCore\Contracts\SettlementProviderRegistryContract;
use LBHurtado\EmiCore\Contracts\StandingFundingAddressProvider;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\PaymentGateway\Funding\NetbankReusableFundingAddressProvider;
use LBHurtado\PaymentGateway\Support\NetbankProviderBalanceReader;
use LBHurtado\PaymentGateway\Support\NetbankProviderLivePreflightProbe;
use LBHurtado\PaymentGateway\Support\NetbankSettlementProvider;

it('advertises only the provider-neutral capabilities implemented by NetBank', function () {
    $provider = app(NetbankSettlementProvider::class);
    $manifest = app(SettlementProviderRegistryContract::class)->get('netbank');

    expect($manifest->label)->toBe('NetBank')
        ->and($manifest->capabilities)->toBe([
            ProviderCapability::ReadinessProbe,
            ProviderCapability::BalanceRead,
            ProviderCapability::FundingEvidenceRead,
            ProviderCapability::FundingInstructionIssue,
            ProviderCapability::StandingFundingAddress,
        ])
        ->and($provider)->toBe(app(NetbankSettlementProvider::class))
        ->and(app(NetbankFundingProviderAdapter::class))
        ->toBeInstanceOf(FundingInstructionIssuer::class)
        ->toBeInstanceOf(ProviderFundingEvidenceVerifier::class)
        ->and(app(NetbankReusableFundingAddressProvider::class))
        ->toBeInstanceOf(StandingFundingAddressProvider::class)
        ->and(app(NetbankProviderBalanceReader::class))
        ->toBeInstanceOf(ProviderBalanceReader::class)
        ->and(app(NetbankProviderLivePreflightProbe::class))
        ->toBeInstanceOf(ProviderLivePreflightProbe::class);
});

it('requires the balance endpoint before provider balance reads are ready', function () {
    config()->set('payment-gateway.netbank.funding.client_id', 'client');
    config()->set('payment-gateway.netbank.funding.client_secret', 'secret');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '123456789');
    config()->set('payment-gateway.netbank.funding.balance_endpoint', null);

    $readiness = app(NetbankSettlementProvider::class)->checkReadiness(
        new ProviderReadinessRequestData(
            provider: 'netbank',
            connectionReference: 'primary',
            requiredCapabilities: [ProviderCapability::BalanceRead],
        ),
    );

    expect($readiness->readyFor([ProviderCapability::BalanceRead]))->toBeFalse()
        ->and($readiness->issues[ProviderCapability::BalanceRead->value])
        ->toBe(['missing-config:balance_endpoint']);
});

it('requires the funding API and authentication endpoints for balance reads', function () {
    config()->set('payment-gateway.netbank.funding.api_url', null);
    config()->set('payment-gateway.netbank.funding.token_url', null);
    config()->set('payment-gateway.netbank.funding.client_id', 'client');
    config()->set('payment-gateway.netbank.funding.client_secret', 'secret');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '123456789');
    config()->set('payment-gateway.netbank.funding.balance_endpoint', '/v1/accounts');

    $readiness = app(NetbankSettlementProvider::class)->checkReadiness(
        new ProviderReadinessRequestData(
            provider: 'netbank',
            connectionReference: 'primary',
            requiredCapabilities: [ProviderCapability::BalanceRead],
        ),
    );

    expect($readiness->readyFor([ProviderCapability::BalanceRead]))->toBeFalse()
        ->and($readiness->issues[ProviderCapability::BalanceRead->value])
        ->toBe([
            'missing-config:api_url',
            'missing-config:token_url',
        ]);
});

it('reports readiness per requested capability without leaking configuration values', function () {
    config()->set('payment-gateway.netbank.funding.client_id', null);
    config()->set('payment-gateway.netbank.funding.client_secret', 'secret-value');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '123456789');

    $readiness = app(NetbankSettlementProvider::class)->checkReadiness(
        new ProviderReadinessRequestData(
            provider: 'netbank',
            connectionReference: 'primary',
            requiredCapabilities: [ProviderCapability::FundingEvidenceRead],
        ),
    );

    expect($readiness->readyFor([ProviderCapability::FundingEvidenceRead]))->toBeFalse()
        ->and($readiness->issues[ProviderCapability::FundingEvidenceRead->value])
        ->toBe(['missing-config:client_id'])
        ->and(json_encode($readiness->toArray()))->not->toContain('secret-value')
        ->not->toContain('123456789');
});

it('reports a configured funding evidence reader as ready', function () {
    config()->set('payment-gateway.netbank.funding.client_id', 'client');
    config()->set('payment-gateway.netbank.funding.client_secret', 'secret');
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '123456789');

    $readiness = app(NetbankSettlementProvider::class)->checkReadiness(
        new ProviderReadinessRequestData(
            provider: 'NETBANK',
            connectionReference: 'primary',
            requiredCapabilities: [ProviderCapability::FundingEvidenceRead],
        ),
    );

    expect($readiness->readyFor([ProviderCapability::FundingEvidenceRead]))->toBeTrue()
        ->and($readiness->issues)->toBe([]);
});
