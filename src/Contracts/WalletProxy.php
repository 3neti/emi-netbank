<?php

namespace LBHurtado\PaymentGateway\Contracts;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\EmiCore\Contracts\ProviderWalletResolver;

/**
 * Provides a wallet for provider operations that need one.
 * Host app binds the implementation (e.g., system user wallet).
 */
interface WalletProxy extends ProviderWalletResolver
{
    public function resolve(): Wallet;
}
