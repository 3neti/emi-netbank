<?php

namespace LBHurtado\PaymentGateway\Contracts;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;

interface WalletResolver
{
    /**
     * Resolve a wallet from normalized deposit recipient data.
     *
     * @throws \RuntimeException If no wallet can be resolved
     */
    public function resolve(RecipientAccountNumberData $data): Wallet;
}
