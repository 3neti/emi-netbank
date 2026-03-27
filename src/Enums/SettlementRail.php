<?php

namespace LBHurtado\PaymentGateway\Enums;

enum SettlementRail: string
{
    case INSTAPAY = 'INSTAPAY';
    case PESONET = 'PESONET';
}
