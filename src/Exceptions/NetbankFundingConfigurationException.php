<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Exceptions;

use LBHurtado\EmiCore\Contracts\ProviderFundingConfigurationFailure;
use RuntimeException;

class NetbankFundingConfigurationException extends RuntimeException implements ProviderFundingConfigurationFailure
{
    public static function missing(string $key): self
    {
        return new self("NetBank funding configuration [{$key}] is required.");
    }
}
