<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Exceptions;

use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;

class NetbankFundingNotVerified extends ProviderFundingNotObserved
{
    public static function noIncomingTransaction(): self
    {
        return new self('NetBank did not return an incoming VCA transaction to verify.');
    }
}
