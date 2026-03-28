<?php

namespace LBHurtado\PaymentGateway\Data;

use Bavix\Wallet\Interfaces\Wallet;
use Spatie\LaravelData\Data;

/**
 * Normalized deposit confirmation result.
 * Provider validates and parses the deposit, host app decides what effects to apply.
 */
class ConfirmedDepositData extends Data
{
    public function __construct(
        public string $reference_code,
        public float $amount,
        public string $currency,
        public string $channel,
        public string $sender_name,
        public string $sender_account,
        public string $sender_institution,
        public string $transfer_type,
        public string $reference_number,
        public string $registration_time,
        public Wallet $wallet,
        public array $raw_payload,
    ) {}
}
