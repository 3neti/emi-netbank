<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use RuntimeException;

final class NetbankTreasuryConnectionAccountResolver
{
    /**
     * @return array{account_number: string, account_name: string, alias: string}
     */
    public static function resolve(string $connectionReference, bool $requiresRouting = false): array
    {
        $connectionReference = trim($connectionReference);
        $configuredAccounts = config(
            'payment-gateway.netbank.funding.treasury_connection_accounts',
            [],
        );

        if (! is_array($configuredAccounts)) {
            throw new RuntimeException('NetBank Treasury connection account configuration is invalid.');
        }

        $configured = $configuredAccounts[$connectionReference] ?? null;

        if ($configured !== null) {
            if (! is_array($configured)) {
                throw new RuntimeException(
                    "NetBank Treasury connection [{$connectionReference}] account configuration is invalid.",
                );
            }

            return self::configuration($configured, $connectionReference, $requiresRouting);
        }

        if (
            str_starts_with($connectionReference, 'netbank-dedicated-')
            || ($configuredAccounts !== [] && $connectionReference !== 'netbank-primary')
        ) {
            throw new RuntimeException(
                "NetBank Treasury connection [{$connectionReference}] has no configured account.",
            );
        }

        return self::configuration([
            'account_number' => config('payment-gateway.netbank.funding.corporate_account_number'),
            'account_name' => config('payment-gateway.netbank.funding.corporate_account_name'),
            'vca_alias' => config('payment-gateway.netbank.funding.vca_alias'),
        ], $connectionReference, $requiresRouting);
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array{account_number: string, account_name: string, alias: string}
     */
    private static function configuration(
        array $configuration,
        string $connectionReference,
        bool $requiresRouting,
    ): array {
        $accountNumber = trim((string) ($configuration['account_number'] ?? ''));
        $accountName = trim((string) ($configuration['account_name'] ?? ''));
        $alias = trim((string) ($configuration['vca_alias'] ?? ''));

        if ($accountNumber === '' || ($requiresRouting && ($accountName === '' || $alias === ''))) {
            throw new RuntimeException(
                "NetBank Treasury connection [{$connectionReference}] has incomplete account configuration.",
            );
        }

        return [
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'alias' => $alias,
        ];
    }
}
