<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class DisbursePayloadDestinationAccountData extends Data
{
    public function __construct(
        #[MapInputName('bank')]
        public string $bank_code,
        public string $account_number,
    ) {}
}
