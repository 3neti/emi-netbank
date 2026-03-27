<?php

namespace LBHurtado\PaymentGateway\Pipelines\ResolvePayable;

use Bavix\Wallet\Models\Wallet;
use Closure;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;

class CheckVoucher
{
    public function handle(RecipientAccountNumberData $recipientAccountNumberData, Closure $next)
    {
        ['class' => $model, 'field' => $field] = config('payment.models.voucher');
        $voucher = $model::where($field, $recipientAccountNumberData->referenceCode)->first();
        $voucher?->refresh();

        return ($voucher && ($voucher->cash->wallet instanceof Wallet))
            ? $voucher->cash
            : $next($recipientAccountNumberData);
    }
}
