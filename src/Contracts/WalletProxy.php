<?php

namespace LBHurtado\PaymentGateway\Contracts;

use Bavix\Wallet\Interfaces\Wallet;

/**
 * Provides a wallet for provider operations that need one.
 * Host app binds the implementation (e.g., system user wallet).
 */
interface WalletProxy
{
    public function resolve(): Wallet;
}
