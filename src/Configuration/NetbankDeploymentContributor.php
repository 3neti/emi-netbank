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
            $this->variable('NETBANK_DISBURSEMENT_ENDPOINT', 'NetBank disbursement endpoint.', 'disbursement.server.end-point'),
            $this->variable('NETBANK_TOKEN_ENDPOINT', 'NetBank OAuth token endpoint.', 'disbursement.server.token-end-point'),
            $this->variable('NETBANK_QR_ENDPOINT', 'NetBank QR Ph generation endpoint.', 'disbursement.server.qr-end-point'),
            $this->variable('NETBANK_STATUS_ENDPOINT', 'NetBank transaction status endpoint.', 'disbursement.server.status-endpoint'),
            $this->variable('NETBANK_BALANCE_ENDPOINT', 'NetBank balance endpoint.', 'disbursement.server.balance-endpoint'),
            $this->variable('NETBANK_CLIENT_ID', 'NetBank OAuth client identifier.', 'disbursement.client.id', secret: true),
            $this->variable('NETBANK_CLIENT_SECRET', 'NetBank OAuth client secret.', 'disbursement.client.secret', secret: true),
            $this->variable('NETBANK_CLIENT_ALIAS', 'NetBank five-digit VCA alias.', 'disbursement.client.alias', secret: true),
            $this->variable('NETBANK_SOURCE_ACCOUNT_NUMBER', 'NetBank source and corporate settlement account.', 'disbursement.source.account_number', secret: true),
            $this->variable('NETBANK_SENDER_CUSTOMER_ID', 'NetBank sender customer identifier.', 'omnipay.gateways.netbank.options.senderCustomerId', secret: true),
            $this->variable('NETBANK_TEST_MODE', 'Use NetBank test-mode request behavior outside production.', 'omnipay.gateways.netbank.options.testMode', 'false', required: false),
            $this->variable('USE_OMNIPAY', 'Use the provider-only NetBank disbursement gateway. Disable only for a controlled legacy rollback.', 'omnipay.use_omnipay', 'true', required: false),
            $this->variable('NETBANK_FUNDING_API_URL', 'Optional NetBank funding API base URL override.', 'payment-gateway.netbank.funding.api_url', 'https://api.netbank.ph', required: false),
            $this->variable('NETBANK_FUNDING_TOKEN_URL', 'Optional NetBank funding OAuth token URL override.', 'payment-gateway.netbank.funding.token_url', 'https://auth.netbank.ph/oauth2/token', required: false),
            $this->variable('NETBANK_FUNDING_CLIENT_ID', 'Optional NetBank funding OAuth client identifier override.', 'payment-gateway.netbank.funding.client_id', required: false),
            $this->variable('NETBANK_FUNDING_CLIENT_SECRET', 'Optional NetBank funding OAuth client secret override.', 'payment-gateway.netbank.funding.client_secret', secret: true, required: false),
            $this->variable('NETBANK_FUNDING_CORPORATE_ACCOUNT_NUMBER', 'Optional NetBank corporate settlement account override.', 'payment-gateway.netbank.funding.corporate_account_number', secret: true, required: false),
            $this->variable('NETBANK_FUNDING_CORPORATE_ACCOUNT_NAME', 'NetBank corporate settlement account display name.', 'payment-gateway.netbank.funding.corporate_account_name', 'X Change Treasury'),
            $this->variable('NETBANK_FUNDING_BALANCE_ENDPOINT', 'Optional NetBank funding balance endpoint override.', 'payment-gateway.netbank.funding.balance_endpoint', required: false),
            $this->variable('NETBANK_FUNDING_VCA_ALIAS', 'Optional NetBank funding VCA alias override.', 'payment-gateway.netbank.funding.vca_alias', secret: true, required: false),
            $this->variable('NETBANK_FUNDING_QR_ENDPOINT', 'Optional NetBank QR Ph generation endpoint override.', 'payment-gateway.netbank.funding.qr_endpoint', 'https://api.netbank.ph/v1/qrph/generate', required: false),
            $this->variable('NETBANK_FUNDING_QR_MERCHANT_NAME', 'QR Ph merchant label.', 'payment-gateway.netbank.funding.qr_merchant_name', 'X Change'),
            $this->variable('NETBANK_FUNDING_QR_MERCHANT_CITY', 'QR Ph merchant city.', 'payment-gateway.netbank.funding.qr_merchant_city', 'Manila'),
            $this->variable('NETBANK_FUNDING_STANDING_HMAC_KEY_ID', 'Standing-address HMAC key identifier required by the HMAC address scheme.', 'payment-gateway.netbank.funding.standing_address.hmac_key_id', required: false),
            $this->variable('NETBANK_FUNDING_STANDING_HMAC_KEY', 'Standing-address HMAC secret required by the HMAC address scheme.', 'payment-gateway.netbank.funding.standing_address.hmac_key', secret: true, required: false),
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
                ProviderCapability::SettlementExecution,
            ],
        )];
    }

    private function variable(
        string $key,
        string $description,
        string $configPath,
        ?string $safeExample = null,
        bool $secret = false,
        bool $required = true,
    ): EnvironmentVariableData {
        return new EnvironmentVariableData(
            key: $key,
            description: $description,
            category: 'NetBank',
            configPath: $configPath,
            safeExample: $safeExample,
            secret: $secret,
            requiredForProviders: $required ? [$this->providerCode()] : [],
        );
    }
}
