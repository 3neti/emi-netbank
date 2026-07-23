<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Exceptions;

use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;

class NetbankFundingAmbiguous extends ProviderFundingVerificationIndeterminate
{
    public static function multipleTransactions(): self
    {
        return new self('NetBank returned multiple VCA transactions that cannot be matched safely.');
    }
}
