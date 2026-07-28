<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Exceptions;

use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use RuntimeException;

final class NetbankBalanceReadException extends RuntimeException
{
    public function __construct(
        public readonly ProviderLivePreflightFailureCode $failureCode,
    ) {
        parent::__construct(
            "NetBank balance read failed [{$failureCode->value}].",
        );
    }
}
