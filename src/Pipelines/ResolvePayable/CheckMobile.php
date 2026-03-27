<?php

namespace LBHurtado\PaymentGateway\Pipelines\ResolvePayable;

use Bavix\Wallet\Models\Wallet;
use Closure;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;

class CheckMobile
{
    public function handle(RecipientAccountNumberData $recipientAccountNumberData, Closure $next)
    {
        $referenceCode = $recipientAccountNumberData->referenceCode;

        // Webhook sends referenceCode like "19173011987" (strips alias + leading 6)
        // We need to convert it back to E.164 format: "639173011987"
        // Pattern: Remove leading "1", prepend "639"
        if (str_starts_with($referenceCode, '1') && strlen($referenceCode) === 11) {
            $mobileE164 = '639'.substr($referenceCode, 1);
        } else {
            // Fallback: use as-is
            $mobileE164 = $referenceCode;
        }

        $user = app(config('payment-gateway.models.user'))::findByMobile($mobileE164);

        if ($user?->wallet instanceof Wallet) {
            return $user;
        }

        return $next($recipientAccountNumberData);
    }
}
