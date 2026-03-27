<?php

namespace LBHurtado\PaymentGateway\Pipelines\ResolvePayable;

use Closure;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;

class ThrowIfUnresolved
{
    public function handle(RecipientAccountNumberData $recipientAccountNumberData, Closure $next)
    {
        throw new \RuntimeException("Could not resolve {$recipientAccountNumberData->referenceCode}");
    }
}
