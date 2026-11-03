<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\EmiCore\Contracts\ProviderWalletResolver;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use RuntimeException;

final readonly class CoreWalletProxy implements WalletProxy
{
    public function __construct(private ProviderWalletResolver $resolver) {}

    public function resolve(): Wallet
    {
        $wallet = $this->resolver->resolve();
        if (! $wallet instanceof Wallet) {
            throw new RuntimeException('The provider wallet resolver did not return a supported wallet.');
        }

        return $wallet;
    }
}
