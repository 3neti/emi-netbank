<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Exceptions;

use RuntimeException;

class NetbankFundingConfigurationException extends RuntimeException
{
    public static function missing(string $key): self
    {
        return new self("NetBank funding configuration [{$key}] is required.");
    }
}
