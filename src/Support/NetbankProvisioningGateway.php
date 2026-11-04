<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\EmiCore\Contracts\ProviderProvisioningGateway;
use LBHurtado\EmiCore\Contracts\ProviderSourceAccountReadiness;
use LBHurtado\EmiCore\Data\Providers\ProviderSourceAccountReadinessRequestData;
use RuntimeException;
use Throwable;

class NetbankProvisioningGateway implements ProviderProvisioningGateway
{
    public function __construct(
        protected NetbankProvisioningProfile $profile,
        protected ?ProviderSourceAccountReadiness $source = null,
    ) {}

    public function providerCode(): string
    {
        return 'netbank';
    }

    public function supports(string $provider, string $mode): bool
    {
        if (strtolower($provider) !== 'netbank') {
            return false;
        }

        return $this->profile->supportsMode($mode);
    }

    public function provision(mixed $owner, array $payload): array
    {
        $provider = 'netbank';
        $mode = (string) data_get($payload, 'mode', 'bank_account_link');
        $topology = (string) data_get($payload, 'topology', 'ledger_pooled');

        if (! $this->supports($provider, $mode)) {
            throw new RuntimeException("NetBank provisioning mode [{$mode}] is not supported.");
        }

        $wallet = null;

        if ($mode === 'ledger_wallet') {
            $wallet = $payload['_local_wallet'] ?? null;
        }

        $bankCode = $this->bankCode($payload);
        $maskedAccountNumber = $this->maskedAccountNumber($payload);
        $bankAccountReady = $mode !== 'bank_account_link'
            || ($bankCode !== null && $maskedAccountNumber !== null);

        $status = match ($mode) {
            'ledger_wallet' => $wallet !== null ? 'ready' : 'pending',
            'bank_account_link' => $bankAccountReady ? 'ready' : 'pending',
            default => 'pending',
        };

        return [
            'provider' => $provider,
            'topology' => $topology,
            'purpose' => data_get($payload, 'purpose'),
            'mode' => $mode,
            'status' => $status,
            'provider_account_id' => null,
            'provider_wallet_id' => null,
            'provider_bank_account_id' => $bankAccountReady
                ? $this->providerBankAccountId($bankCode, $maskedAccountNumber)
                : null,
            'external_uid' => $this->externalUid($owner, $mode),
            'verification_status' => $status === 'ready' ? 'APPROVED' : 'PENDING',
            'capabilities' => [
                'issue_pay_code' => $mode === 'ledger_wallet'
                    ? $status === 'ready'
                    : false,
                'redeem_pay_code' => $mode === 'bank_account_link'
                    ? $status === 'ready'
                    : false,
            ],
            'metadata' => array_filter([
                'wallet' => $wallet !== null ? [
                    'id' => is_object($wallet) ? ($wallet->id ?? null) : null,
                    'slug' => is_object($wallet) ? ($wallet->slug ?? null) : null,
                ] : null,
                'bank_code' => $bankCode,
                'account_number_masked' => $maskedAccountNumber,
                'source_account' => $this->sourceAccountReadiness(),
            ], static fn (mixed $value): bool => $value !== null),
        ];
    }

    public function refresh(mixed $link): array
    {
        return [
            'status' => data_get($link, 'status', 'ready'),
            'refreshed' => true,
            'source_account' => $this->sourceAccountReadiness(),
        ];
    }

    protected function bankCode(array $payload): ?string
    {
        $bankCode = data_get($payload, 'bank_code')
            ?? data_get($payload, 'bank_account.bank_code');

        return is_string($bankCode) && trim($bankCode) !== ''
            ? trim($bankCode)
            : null;
    }

    protected function maskedAccountNumber(array $payload): ?string
    {
        $masked = data_get($payload, 'account_number_masked');

        if (is_string($masked) && trim($masked) !== '') {
            return trim($masked);
        }

        $accountNumber = data_get($payload, 'account_number')
            ?? data_get($payload, 'bank_account.account_number');

        if (! is_string($accountNumber) || trim($accountNumber) === '') {
            return null;
        }

        $digits = trim($accountNumber);
        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($digits, -4);
    }

    protected function providerBankAccountId(?string $bankCode, ?string $maskedAccountNumber): ?string
    {
        if ($bankCode === null || $maskedAccountNumber === null) {
            return null;
        }

        return $this->profile->legacyBankAccountReference($bankCode, $maskedAccountNumber);
    }

    protected function externalUid(mixed $owner, string $mode): string
    {
        $ownerKey = is_object($owner) && method_exists($owner, 'getKey')
            ? (string) $owner->getKey()
            : 'owner';

        return $this->profile->legacyPrincipalReference($ownerKey, $mode);
    }

    /** @return array<string, mixed>|null */
    protected function sourceAccountReadiness(): ?array
    {
        $source = $this->source;
        if ($source === null) {
            $sources = collect(app()->tagged('emi.provider-source-account-readiness'))
                ->filter(fn (mixed $candidate): bool => $candidate instanceof ProviderSourceAccountReadiness && $candidate->providerCode() === 'netbank')
                ->uniqueStrict();
            if ($sources->count() > 1) {
                throw new RuntimeException('Multiple source account readiness providers are registered.');
            }
            $source = $sources->first();
        }
        if ($source === null) {
            return (bool) config('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', false)
                ? ['ready' => false, 'checked' => false, 'message' => 'No provider source-account readiness capability is available.']
                : null;
        }

        try {
            return $source->inspectSourceAccount(new ProviderSourceAccountReadinessRequestData(provisioning: true));
        } catch (Throwable) {
            return ['ready' => false, 'checked' => true, 'message' => 'Provider source-account readiness is unavailable.'];
        }
    }
}
