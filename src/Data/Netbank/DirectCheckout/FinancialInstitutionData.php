<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\DirectCheckout;

use Spatie\LaravelData\Data;

class FinancialInstitutionData extends Data
{
    public function __construct(
        public string $institution_code,
        public string $name,
        public ?string $logo_url = null,
    ) {}
}
