<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\DirectCheckout;

use Spatie\LaravelData\Data;

class CollectionResponseData extends Data
{
    public function __construct(
        public string $redirect_url,
        public string $reference_no,
    ) {}
}
