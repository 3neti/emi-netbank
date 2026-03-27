<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

use LBHurtado\PaymentGateway\Support\Address;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class DisbursePayloadRecipientData extends Data
{
    public array $address;

    public function __construct(
        #[MapInputName('account_number')]
        public string $name
    ) {
        $this->address = Address::generate();
    }

}
