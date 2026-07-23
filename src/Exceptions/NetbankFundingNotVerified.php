<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Exceptions;

use RuntimeException;

class NetbankFundingNotVerified extends RuntimeException
{
    public static function noIncomingTransaction(): self
    {
        return new self('NetBank did not return an incoming VCA transaction to verify.');
    }

    public static function ambiguous(): self
    {
        return new self('NetBank returned multiple VCA transactions that cannot be matched safely.');
    }
}
