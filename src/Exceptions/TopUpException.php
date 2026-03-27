<?php

namespace LBHurtado\PaymentGateway\Exceptions;

use Exception;

class TopUpException extends Exception
{
    public static function gatewayNotSupported(string $gateway): self
    {
        return new self("Gateway '{$gateway}' does not support top-up/collection.");
    }

    public static function initiationFailed(string $reason = 'Unknown error'): self
    {
        return new self("Top-up initiation failed: {$reason}");
    }

    public static function invalidAmount(float $amount, float $min, float $max): self
    {
        return new self("Amount {$amount} is invalid. Must be between {$min} and {$max}.");
    }

    public static function referenceNotFound(string $referenceNo): self
    {
        return new self("Top-up with reference '{$referenceNo}' not found.");
    }

    public static function alreadyProcessed(string $referenceNo): self
    {
        return new self("Top-up '{$referenceNo}' has already been processed.");
    }
}
