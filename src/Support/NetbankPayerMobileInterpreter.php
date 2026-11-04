<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\EmiCore\Contracts\ProviderPayerMobileInterpreter;

final class NetbankPayerMobileInterpreter implements ProviderPayerMobileInterpreter
{
    public function providerCode(): string
    {
        return 'netbank';
    }

    public function resolve(?string $institutionCode, ?string $accountNumber): ?string
    {
        $institution = strtoupper(trim((string) $institutionCode));
        $account = trim((string) $accountNumber);
        if (! in_array($institution, ['GXCHPHM2XXX', 'PAPHPHM1XXX', 'GCASH', 'MAYA', 'PAYMAYA'], true)
            || preg_match('/^(?:0|63|\+63)9[0-9]{9}$/', $account) !== 1) {
            return null;
        }

        return '63'.substr($account, -10);
    }
}
