<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\EmiCore\Contracts\ProviderProvisioningGateway;
use LBHurtado\EmiCore\Contracts\ProviderProvisioningGatewayFactory;
use LBHurtado\EmiCore\Contracts\ProviderSourceAccountReadiness;

final class NetbankProvisioningGatewayFactory implements ProviderProvisioningGatewayFactory
{
    public function providerCode(): string
    {
        return 'netbank';
    }

    public function requiresLocalWallet(string $mode): bool
    {
        return $mode === 'ledger_wallet';
    }

    public function make(?ProviderSourceAccountReadiness $source = null): ProviderProvisioningGateway
    {
        return new NetbankProvisioningGateway(app(NetbankProvisioningProfile::class), $source);
    }
}
