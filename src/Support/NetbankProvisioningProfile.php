<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\EmiCore\Contracts\ProviderClaimantBankProvisioningPolicy;
use LBHurtado\EmiCore\Contracts\ProviderDefaultProvisioningMode;
use LBHurtado\EmiCore\Contracts\ProviderProvisioningPresentation;
use LBHurtado\EmiCore\Contracts\ProviderProvisioningProfile;

final class NetbankProvisioningProfile implements ProviderClaimantBankProvisioningPolicy, ProviderDefaultProvisioningMode, ProviderProvisioningPresentation, ProviderProvisioningProfile
{
    public function defaultProvisioningTopology(): string
    {
        return 'ledger_pooled';
    }

    public function provisioningPresentation(string $mode): ?array
    {
        return $mode === 'bank_account_link' ? [
            'title' => 'Add payout destination',
            'description' => 'Add a payout destination for NetBank ledger-backed disbursement.',
            'steps' => ['bank_account', 'consent', 'ready'],
            'fields' => ['bank_code', 'account_number', 'account_name', 'consent'],
            'actions' => ['continue'],
        ] : null;
    }

    public function defaultProvisioningMode(): string
    {
        return 'bank_account_link';
    }

    public function providerCode(): string
    {
        return 'netbank';
    }

    public function requiresClaimantBankProvisioning(): bool
    {
        return true;
    }

    public function supportsMode(string $mode): bool
    {
        return in_array($mode, ['ledger_wallet', 'bank_account_link'], true);
    }

    public function legacyBankAccountReference(?string $bankCode, ?string $maskedAccountNumber): ?string
    {
        return $bankCode === null || $maskedAccountNumber === null
            ? null
            : sprintf('NETBANK-%s-%s', $bankCode, str_replace('*', 'X', $maskedAccountNumber));
    }

    public function legacyPrincipalReference(string $ownerKey, string $mode): string
    {
        return sprintf('netbank-%s-%s', $ownerKey, $mode);
    }
}
