<?php

namespace LBHurtado\PaymentGateway\Enums;

/**
 * @deprecated Use LBHurtado\EmiCore\Enums\SettlementRail instead.
 */
enum SettlementRail: string
{
    case INSTAPAY = 'INSTAPAY';
    case PESONET = 'PESONET';

    public function toEmiCore(): \LBHurtado\EmiCore\Enums\SettlementRail
    {
        return \LBHurtado\EmiCore\Enums\SettlementRail::from($this->value);
    }
}
