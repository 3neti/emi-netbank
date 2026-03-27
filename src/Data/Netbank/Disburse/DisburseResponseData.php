<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

use Spatie\LaravelData\Data;

class DisburseResponseData extends Data
{
    public function __construct(
        public string $uuid,
        public string $transaction_id,
        public string $status,
    ) {}
}
