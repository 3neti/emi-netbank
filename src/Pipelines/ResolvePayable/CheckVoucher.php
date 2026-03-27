<?php

namespace LBHurtado\PaymentGateway\Pipelines\ResolvePayable;

use Bavix\Wallet\Models\Wallet;
use Closure;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;

/**
 * Resolves a payable entity from the recipient account number.
 * Uses config('payment.models.voucher') to avoid hard coupling to any specific model.
 */
class CheckVoucher
{
    public function handle(RecipientAccountNumberData $recipientAccountNumberData, Closure $next)
    {
        $config = config('payment.models.voucher');
        if (! $config || ! class_exists($config['class'] ?? '')) {
            return $next($recipientAccountNumberData);
        }

        ['class' => $model, 'field' => $field] = $config;
        $voucher = $model::where($field, $recipientAccountNumberData->referenceCode)->first();
        $voucher?->refresh();

        return ($voucher && ($voucher->cash->wallet instanceof Wallet))
            ? $voucher->cash
            : $next($recipientAccountNumberData);
    }
}
