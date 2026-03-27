<?php

namespace LBHurtado\PaymentGateway\Data\Wallet;

use Spatie\LaravelData\Data;

class BalanceData extends Data
{
    public function __construct(
        public int $amount,      // in minor units (centavos)
        public string $currency,
        public ?string $account = null,
        public ?string $retrieved_at = null,
        public array $meta = [],
    ) {}

    /**
     * Convert amount to major units (e.g., centavos to pesos)
     */
    public function toMajor(): float
    {
        return $this->amount / 100;
    }

    /**
     * Get formatted balance string
     */
    public function formatted(): string
    {
        return number_format($this->toMajor(), 2);
    }

    /**
     * Legacy compatibility: get balance as float
     *
     * @deprecated Use toMajor() instead
     */
    public function getBalance(): float
    {
        return $this->toMajor();
    }
}
