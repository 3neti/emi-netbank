<?php

namespace LBHurtado\PaymentGateway\Data\TopUp;

use Spatie\LaravelData\Data;

class TopUpResultData extends Data
{
    public function __construct(
        public string $reference_no,
        public string $redirect_url,
        public string $gateway,
        public float $amount,
        public string $currency = 'PHP',
        public ?string $institution_code = null,
    ) {}
}
