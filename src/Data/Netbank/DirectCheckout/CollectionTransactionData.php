<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\DirectCheckout;

use Spatie\LaravelData\Data;

class CollectionTransactionData extends Data
{
    public function __construct(
        public string $payment_id,
        public string $payment_status, // PENDING, PAID, EXPIRED, FAILED
        public string $reference_no,
        public int $amount_value,
        public string $amount_currency,
        public ?string $institution_code = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
    ) {}

    public function isPaid(): bool
    {
        return strtoupper($this->payment_status) === 'PAID';
    }

    public function isPending(): bool
    {
        return strtoupper($this->payment_status) === 'PENDING';
    }

    public function isFailed(): bool
    {
        return in_array(strtoupper($this->payment_status), ['FAILED', 'EXPIRED']);
    }
}
