<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Exceptions;

use RuntimeException;

class NetbankFundingRequestFailed extends RuntimeException
{
    public static function forOperation(string $operation, int $status): self
    {
        return new self("NetBank funding operation [{$operation}] failed with HTTP {$status}.");
    }

    public static function invalidResponse(string $operation): self
    {
        return new self("NetBank funding operation [{$operation}] returned an invalid response.");
    }
}
