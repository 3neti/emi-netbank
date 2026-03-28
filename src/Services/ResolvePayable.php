<?php

namespace LBHurtado\PaymentGateway\Services;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Pipeline\Pipeline;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use LBHurtado\PaymentGateway\Pipelines\ResolvePayable\CheckMobile;
use LBHurtado\PaymentGateway\Pipelines\ResolvePayable\CheckSubject;
use LBHurtado\PaymentGateway\Pipelines\ResolvePayable\ThrowIfUnresolved;

class ResolvePayable
{
    public function execute(RecipientAccountNumberData $recipientAccountNumberData): Wallet
    {
        $pipeline = config('payment.resolve_payable_pipeline', [
            CheckMobile::class,
            CheckSubject::class,
            ThrowIfUnresolved::class,
        ]);

        return app(Pipeline::class)
            ->send($recipientAccountNumberData)
            ->through($pipeline)
            ->thenReturn();
    }
}
