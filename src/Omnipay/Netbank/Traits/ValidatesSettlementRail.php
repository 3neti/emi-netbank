<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Traits;

use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use Omnipay\Common\Exception\InvalidRequestException;

/**
 * ValidatesSettlementRail Trait
 *
 * Provides validation for settlement rail support, amount limits,
 * and fee calculation for disbursement requests.
 */
trait ValidatesSettlementRail
{
    /**
     * Validate settlement rail for a disbursement
     *
     * @param  string  $bankCode  The recipient bank SWIFT/BIC code
     * @param  SettlementRail  $rail  The settlement rail to use
     * @param  int  $amount  The amount in minor units (centavos)
     *
     * @throws InvalidRequestException
     */
    protected function validateSettlementRail(
        string $bankCode,
        SettlementRail $rail,
        int $amount
    ): void {
        // 1. Check if bank supports the rail
        $bankRegistry = app(BankRegistry::class);
        $supportedRails = $bankRegistry->supportedSettlementRails($bankCode);

        if (! isset($supportedRails[$rail->value])) {
            $bankInfo = $bankRegistry->find($bankCode);
            $bankName = $bankInfo['full_name'] ?? $bankCode;

            throw new InvalidRequestException(
                "Bank '{$bankName}' ({$bankCode}) does not support {$rail->value} settlement rail"
            );
        }

        // 2. Check if gateway supports the rail
        $railConfig = $this->getParameter('rails')[$rail->value] ?? null;

        if (! $railConfig || ! ($railConfig['enabled'] ?? false)) {
            throw new InvalidRequestException(
                "Gateway does not support {$rail->value} settlement rail"
            );
        }

        // 3. Validate amount limits
        $minAmount = $railConfig['min_amount'] ?? 0;
        $maxAmount = $railConfig['max_amount'] ?? PHP_INT_MAX;

        if ($amount < $minAmount) {
            $minFormatted = number_format($minAmount / 100, 2);
            throw new InvalidRequestException(
                "Amount too small for {$rail->value}. Minimum: ₱{$minFormatted}"
            );
        }

        if ($amount > $maxAmount) {
            $maxFormatted = number_format($maxAmount / 100, 2);
            throw new InvalidRequestException(
                "Amount exceeds {$rail->value} limit. Maximum: ₱{$maxFormatted}"
            );
        }
    }

    /**
     * Get the fee for a specific settlement rail
     *
     * @return int Fee in minor units (centavos)
     */
    protected function getRailFee(SettlementRail $rail): int
    {
        $railConfig = $this->getParameter('rails')[$rail->value] ?? null;

        return $railConfig['fee'] ?? 0;
    }

    /**
     * Check if a specific rail is enabled
     */
    protected function isRailEnabled(SettlementRail $rail): bool
    {
        $railConfig = $this->getParameter('rails')[$rail->value] ?? null;

        return $railConfig && ($railConfig['enabled'] ?? false);
    }
}
