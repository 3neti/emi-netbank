<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Exceptions;

use RuntimeException;

class NetbankFundingRequestFailed extends RuntimeException
{
    private function __construct(
        public readonly string $operation,
        public readonly ?int $status,
        public readonly bool $invalidResponse,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forOperation(string $operation, int $status): self
    {
        return new self(
            operation: $operation,
            status: $status,
            invalidResponse: false,
            message: "NetBank funding operation [{$operation}] failed with HTTP {$status}.",
        );
    }

    public static function invalidResponse(string $operation): self
    {
        return new self(
            operation: $operation,
            status: null,
            invalidResponse: true,
            message: "NetBank funding operation [{$operation}] returned an invalid response.",
        );
    }
}
