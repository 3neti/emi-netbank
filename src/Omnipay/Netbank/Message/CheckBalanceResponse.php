<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use LBHurtado\PaymentGateway\Data\Wallet\BalanceData;
use Omnipay\Common\Message\AbstractResponse;

/**
 * NetBank Check Balance Response
 *
 * Response from NetBank balance check API.
 *
 * Example response data:
 * <code>
 * {
 *   "status": "success",
 *   "data": {
 *     "account_number": "1234567890",
 *     "balance": 1250000,
 *     "available_balance": 1200000,
 *     "currency": "PHP",
 *     "as_of": "2024-11-13T12:00:00Z"
 *   }
 * }
 * </code>
 */
class CheckBalanceResponse extends AbstractResponse
{
    /**
     * Check if the request was successful
     */
    public function isSuccessful(): bool
    {
        // NetBank returns account data directly (not wrapped in status/data)
        return isset($this->data['account_number'])
            && isset($this->data['balance']);
    }

    /**
     * Get the balance in minor units (centavos)
     * NetBank returns balance as {"cur": "PHP", "num": "135000"}
     */
    public function getBalance(): ?int
    {
        if (isset($this->data['balance']['num'])) {
            return (int) $this->data['balance']['num'];
        }

        return null;
    }

    /**
     * Get the available balance in minor units (centavos)
     * Available balance excludes pending transactions
     * NetBank returns available_balance as {"cur": "PHP", "num": "135000"}
     */
    public function getAvailableBalance(): ?int
    {
        if (isset($this->data['available_balance']['num'])) {
            return (int) $this->data['available_balance']['num'];
        }

        return $this->getBalance();
    }

    /**
     * Get the currency code
     * NetBank returns currency in balance object: {"cur": "PHP", "num": "135000"}
     */
    public function getCurrency(): string
    {
        return $this->data['balance']['cur'] ?? 'PHP';
    }

    /**
     * Get the account number
     */
    public function getAccountNumber(): ?string
    {
        return $this->data['account_number'] ?? null;
    }

    /**
     * Get the timestamp when balance was calculated
     * NetBank doesn't provide this, use created_date or null
     */
    public function getAsOf(): ?string
    {
        return $this->data['created_date'] ?? null;
    }

    /**
     * Get balance as BalanceData object
     */
    public function getBalanceData(): ?BalanceData
    {
        if (! $this->isSuccessful()) {
            return null;
        }

        return new BalanceData(
            amount: $this->getBalance(),
            currency: $this->getCurrency(),
        );
    }

    /**
     * Get available balance as BalanceData object
     */
    public function getAvailableBalanceData(): ?BalanceData
    {
        if (! $this->isSuccessful()) {
            return null;
        }

        return new BalanceData(
            amount: $this->getAvailableBalance(),
            currency: $this->getCurrency(),
        );
    }

    /**
     * Get the error message if request failed
     */
    public function getMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->data['message']
            ?? $this->data['error']
            ?? 'Unknown error occurred';
    }

    /**
     * Get the error code if request failed
     */
    public function getCode(): ?string
    {
        return $this->data['code'] ?? null;
    }
}
