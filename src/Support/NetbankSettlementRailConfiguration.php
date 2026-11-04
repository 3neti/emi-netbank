<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\EmiCore\Contracts\ProviderSettlementRailConfiguration;
use LBHurtado\EmiCore\Enums\SettlementRail;

final class NetbankSettlementRailConfiguration implements ProviderSettlementRailConfiguration
{
    public function providerCode(): string
    {
        return 'netbank';
    }

    public function configurationFor(SettlementRail $rail): array
    {
        return (array) config("omnipay.gateways.netbank.options.rails.{$rail->value}", []);
    }
}
