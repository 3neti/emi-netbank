<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Configuration;

use LBHurtado\EmiCore\Contracts\DeploymentConnectionContributor;
use LBHurtado\EmiCore\Contracts\DeploymentEnvironmentContributor;
use LBHurtado\EmiCore\Data\Configuration\EnvironmentVariableData;
use LBHurtado\EmiCore\Data\Configuration\ProviderConnectionTemplateData;
use LBHurtado\EmiCore\Enums\ProviderCapability;

final class NetbankDeploymentContributor implements DeploymentConnectionContributor, DeploymentEnvironmentContributor
{
    public function providerCode(): string
    {
        return 'netbank';
    }

    public function environmentVariables(): array
    {
        return [
            $this->variable('NETBANK_FUNDING_API_URL', 'NetBank funding API base URL.', 'payment-gateway.netbank.funding.api_url', 'https://api.netbank.ph'),
            $this->variable('NETBANK_FUNDING_TOKEN_URL', 'NetBank OAuth token URL.', 'payment-gateway.netbank.funding.token_url', 'https://auth.netbank.ph/oauth2/token'),
            $this->variable('NETBANK_FUNDING_CLIENT_ID', 'NetBank OAuth client identifier.', 'payment-gateway.netbank.funding.client_id'),
            $this->variable('NETBANK_FUNDING_CLIENT_SECRET', 'NetBank OAuth client secret.', 'payment-gateway.netbank.funding.client_secret', secret: true),
            $this->variable('NETBANK_FUNDING_CORPORATE_ACCOUNT_NUMBER', 'NetBank corporate settlement account number.', 'payment-gateway.netbank.funding.corporate_account_number', secret: true),
            $this->variable('NETBANK_FUNDING_BALANCE_ENDPOINT', 'NetBank balance endpoint.', 'payment-gateway.netbank.funding.balance_endpoint'),
            $this->variable('NETBANK_FUNDING_VCA_ALIAS', 'NetBank five-digit VCA alias.', 'payment-gateway.netbank.funding.vca_alias', secret: true),
            $this->variable('NETBANK_FUNDING_QR_ENDPOINT', 'NetBank QR Ph generation endpoint.', 'payment-gateway.netbank.funding.qr_endpoint', 'https://api.netbank.ph/v1/qrph/generate'),
            $this->variable('NETBANK_FUNDING_QR_MERCHANT_NAME', 'QR Ph merchant label.', 'payment-gateway.netbank.funding.qr_merchant_name', 'X Change'),
            $this->variable('NETBANK_FUNDING_QR_MERCHANT_CITY', 'QR Ph merchant city.', 'payment-gateway.netbank.funding.qr_merchant_city', 'Manila'),
            $this->variable('NETBANK_FUNDING_STANDING_HMAC_KEY_ID', 'Standing-address HMAC key identifier.', 'payment-gateway.netbank.funding.standing_address.hmac_key_id'),
            $this->variable('NETBANK_FUNDING_STANDING_HMAC_KEY', 'Standing-address HMAC secret.', 'payment-gateway.netbank.funding.standing_address.hmac_key', secret: true),
        ];
    }

    public function connectionTemplates(): array
    {
        return [new ProviderConnectionTemplateData(
            reference: 'netbank-primary',
            provider: $this->providerCode(),
            currency: 'PHP',
            inventoryReference: 'inventory:netbank:vca-cash',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            settlementResourceType: 'cash_at_bank',
            custodyMode: 'provider_projection',
            requiredCapabilities: [
                ProviderCapability::ReadinessProbe,
                ProviderCapability::BalanceRead,
                ProviderCapability::FundingEvidenceRead,
                ProviderCapability::FundingInstructionIssue,
            ],
        )];
    }

    private function variable(
        string $key,
        string $description,
        string $configPath,
        ?string $safeExample = null,
        bool $secret = false,
    ): EnvironmentVariableData {
        return new EnvironmentVariableData(
            key: $key,
            description: $description,
            category: 'NetBank',
            configPath: $configPath,
            safeExample: $safeExample,
            secret: $secret,
            requiredForProviders: [$this->providerCode()],
        );
    }
}
