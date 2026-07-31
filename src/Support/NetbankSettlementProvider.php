<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProvider;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityManifestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;

final class NetbankSettlementProvider implements ProviderReadinessProbe, SettlementProvider
{
    private const Provider = 'netbank';

    public function providerCode(): string
    {
        return self::Provider;
    }

    public function manifest(): ProviderCapabilityManifestData
    {
        return new ProviderCapabilityManifestData(
            provider: self::Provider,
            label: 'NetBank',
            capabilities: [
                ProviderCapability::ReadinessProbe,
                ProviderCapability::BalanceRead,
                ProviderCapability::FundingEvidenceRead,
                ProviderCapability::FundingInstructionIssue,
                ProviderCapability::StandingFundingAddress,
                ProviderCapability::SettlementExecution,
            ],
        );
    }

    public function checkReadiness(
        ProviderReadinessRequestData $request,
    ): ProviderCapabilityReadinessData {
        if (mb_strtolower(trim($request->provider)) !== self::Provider) {
            throw new InvalidArgumentException('NetBank readiness provider mismatch.');
        }

        $capabilities = $request->requiredCapabilities === []
            ? $this->manifest()->capabilities
            : $request->requiredCapabilities;
        $checks = [];
        $issues = [];

        foreach ($capabilities as $capability) {
            $missing = $this->missingConfiguration($capability);
            $checks[$capability->value] = $missing === [];

            if ($missing !== []) {
                $issues[$capability->value] = $missing;
            }
        }

        return new ProviderCapabilityReadinessData(
            provider: self::Provider,
            connectionReference: $request->connectionReference,
            checks: $checks,
            issues: $issues,
            checkedAt: DateTimeImmutable::createFromInterface(now()),
        );
    }

    /**
     * @return list<string>
     */
    private function missingConfiguration(ProviderCapability $capability): array
    {
        if ($capability === ProviderCapability::ReadinessProbe) {
            return [];
        }

        if (! $this->manifest()->supports($capability)) {
            return ['capability-not-supported'];
        }

        $keys = match ($capability) {
            ProviderCapability::BalanceRead => [
                'api_url',
                'token_url',
                'client_id',
                'client_secret',
                'corporate_account_number',
                'balance_endpoint',
            ],
            ProviderCapability::FundingEvidenceRead => [
                'client_id',
                'client_secret',
                'corporate_account_number',
            ],
            ProviderCapability::FundingInstructionIssue => [
                'client_id',
                'client_secret',
                'corporate_account_number',
                'vca_alias',
                'qr_endpoint',
                'qr_merchant_name',
                'qr_merchant_city',
            ],
            ProviderCapability::StandingFundingAddress => [
                'client_id',
                'client_secret',
                'corporate_account_number',
                'vca_alias',
                'reference_key',
                'qr_endpoint',
                'qr_merchant_name',
                'qr_merchant_city',
            ],
            ProviderCapability::SettlementExecution => [],
            default => [],
        };

        if ($capability === ProviderCapability::SettlementExecution) {
            $configuration = [
                'disbursement_endpoint' => 'disbursement.server.end-point',
                'token_endpoint' => 'disbursement.server.token-end-point',
                'status_endpoint' => 'disbursement.server.status-endpoint',
                'client_id' => 'disbursement.client.id',
                'client_secret' => 'disbursement.client.secret',
                'source_account_number' => 'disbursement.source.account_number',
                'sender_customer_id' => 'omnipay.gateways.netbank.options.senderCustomerId',
            ];

            return array_values(array_map(
                static fn (string $key): string => "missing-config:{$key}",
                array_keys(array_filter(
                    $configuration,
                    static fn (string $path): bool => trim((string) config($path)) === '',
                )),
            ));
        }

        if (
            $capability === ProviderCapability::StandingFundingAddress
            && config('payment-gateway.netbank.funding.standing_address.scheme') === 'netbank-account-hmac-v2'
        ) {
            $keys[] = 'standing_address.hmac_key_id';
            $keys[] = 'standing_address.hmac_key';
        }

        return array_values(array_map(
            static fn (string $key): string => "missing-config:{$key}",
            array_filter(
                $keys,
                static fn (string $key): bool => trim((string) config(
                    "payment-gateway.netbank.funding.{$key}",
                )) === '',
            ),
        ));
    }
}
