<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Enums;

enum NetbankStandingAddressScheme: string
{
    case MobileV1 = 'netbank-mobile-v1';
    case AccountHmacV2 = 'netbank-account-hmac-v2';
}
