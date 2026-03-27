<?php

namespace LBHurtado\PaymentGateway\Services;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Pipeline\Pipeline;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use LBHurtado\PaymentGateway\Pipelines\ResolvePayable\CheckMobile;
use LBHurtado\PaymentGateway\Pipelines\ResolvePayable\CheckVoucher;
use LBHurtado\PaymentGateway\Pipelines\ResolvePayable\ThrowIfUnresolved;

class ResolvePayable
{
    public function execute(RecipientAccountNumberData $recipientAccountNumberData): Wallet
    {
        return app(Pipeline::class)
            ->send($recipientAccountNumberData)
            ->through([
                CheckMobile::class,
                CheckVoucher::class,
                ThrowIfUnresolved::class,
            ])->thenReturn();
    }
}
