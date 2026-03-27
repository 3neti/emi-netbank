<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Deposit;

use Spatie\LaravelData\Data;

class DepositSenderData extends Data
{
    public function __construct(
        public string $accountNumber,
        public string $institutionCode,
        public string $name,
    ) {}
}
