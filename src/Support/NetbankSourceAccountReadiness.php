<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\EmiCore\Contracts\ProviderSourceAccountReadiness;
use LBHurtado\EmiCore\Data\Providers\ProviderSourceAccountReadinessRequestData;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use Throwable;

class NetbankSourceAccountReadiness implements ProviderSourceAccountReadiness
{
    public function __construct(
        protected ?PaymentGatewayInterface $gateway = null,
    ) {}

    public function providerCode(): string
    {
        return 'netbank';
    }

    public function inspectSourceAccount(ProviderSourceAccountReadinessRequestData $request): ?array
    {
        return $request->provisioning ? $this->provisioningReadiness() : $this->handle($request->requiredAmountMinor);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function provisioningReadiness(): ?array
    {
        if (! (bool) config('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', false)) {
            return null;
        }

        $accountNumber = config('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number')
            ?: config('disbursement.account_number');

        if (! is_string($accountNumber) || trim($accountNumber) === '') {
            return [
                'ready' => false,
                'checked' => false,
                'message' => 'No NetBank source account number configured for readiness checks.',
            ];
        }

        try {
            if (! $this->gateway instanceof PaymentGatewayInterface) {
                return [
                    'ready' => false,
                    'checked' => false,
                    'account_number_masked' => $this->maskAccountNumber(trim($accountNumber)),
                    'message' => 'NetBank source-account readiness check is enabled, but no payment gateway is bound.',
                ];
            }

            $balance = $this->gateway->checkAccountBalance(trim($accountNumber));

            return [
                'ready' => true,
                'checked' => true,
                'account_number_masked' => $this->maskAccountNumber(trim($accountNumber)),
                'balance' => $balance['balance'] ?? null,
                'available_balance' => $balance['available_balance'] ?? null,
                'currency' => $balance['currency'] ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'ready' => false,
                'checked' => true,
                'account_number_masked' => $this->maskAccountNumber(trim($accountNumber)),
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(?int $requiredMinor = null): array
    {
        $accountNumber = $this->sourceAccountNumber();

        if (! $this->enabled()) {
            return [
                'enabled' => false,
                'ready' => true,
                'checked' => false,
                'account_number' => $accountNumber,
                'account_number_masked' => $accountNumber !== null ? $this->maskAccountNumber($accountNumber) : null,
                'message' => 'NetBank source-account readiness check is disabled.',
            ];
        }

        if ($accountNumber === null) {
            return [
                'enabled' => true,
                'ready' => false,
                'checked' => false,
                'message' => 'No NetBank source account number is configured.',
            ];
        }

        if (! $this->gateway instanceof PaymentGatewayInterface) {
            return [
                'enabled' => true,
                'ready' => false,
                'checked' => false,
                'account_number' => $accountNumber,
                'account_number_masked' => $this->maskAccountNumber($accountNumber),
                'message' => 'No NetBank payment gateway is available for source-account readiness.',
            ];
        }

        try {
            $balance = $this->gateway->checkAccountBalance($accountNumber);

            if ($this->isFailedProviderResponse($balance)) {
                return [
                    'enabled' => true,
                    'ready' => false,
                    'checked' => true,
                    'reason' => 'balance_unavailable',
                    'account_number' => $accountNumber,
                    'account_number_masked' => $this->maskAccountNumber($accountNumber),
                    'currency' => $this->resolveCurrency($balance),
                    'message' => 'NetBank source account balance check failed.',
                ];
            }

            $balanceMinor = $this->normalizeMoneyToMinor($balance['balance'] ?? data_get($balance, 'raw.balance') ?? 0);
            $availableMinor = $this->normalizeMoneyToMinor($balance['available_balance'] ?? data_get($balance, 'raw.available_balance') ?? $balanceMinor);
            $ready = $requiredMinor === null || $availableMinor >= $requiredMinor;

            return [
                'enabled' => true,
                'ready' => $ready,
                'checked' => true,
                'fetched_at' => now()->toIso8601String(),
                'account_number' => $accountNumber,
                'account_number_masked' => $this->maskAccountNumber($accountNumber),
                'balance_minor' => $balanceMinor,
                'available_balance_minor' => $availableMinor,
                'required_minor' => $requiredMinor,
                'currency' => $this->resolveCurrency($balance),
                'as_of' => $balance['as_of'] ?? now()->toIso8601String(),
                'message' => $this->message($ready, $requiredMinor),
            ];
        } catch (Throwable $e) {
            return [
                'enabled' => true,
                'ready' => false,
                'checked' => true,
                'reason' => 'balance_unavailable',
                'account_number' => $accountNumber,
                'account_number_masked' => $this->maskAccountNumber($accountNumber),
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function enabled(): bool
    {
        return (bool) config('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', false);
    }

    protected function sourceAccountNumber(): ?string
    {
        $value = config('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number')
            ?: config('disbursement.source.account_number')
            ?: config('omnipay.gateways.netbank.options.sourceAccountNumber');

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeMoneyToMinor(mixed $value): int
    {
        if (is_array($value)) {
            return $this->normalizeMoneyToMinor($value['num'] ?? $value['amount'] ?? $value['value'] ?? 0);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value * 100);
        }

        if (is_string($value)) {
            $normalized = preg_replace('/[^\d.\-]/', '', trim($value));

            if ($normalized === null || $normalized === '' || $normalized === '-' || $normalized === '.') {
                return 0;
            }

            if (str_contains($normalized, '.')) {
                return (int) round(((float) $normalized) * 100);
            }

            return (int) $normalized;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    protected function resolveCurrency(array $balance): string
    {
        return (string) (
            $balance['currency']
            ?? data_get($balance, 'balance.cur')
            ?? data_get($balance, 'available_balance.cur')
            ?? data_get($balance, 'raw.balance.cur')
            ?? data_get($balance, 'raw.available_balance.cur')
            ?? config('x-change.pricing.currency', 'PHP')
        );
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    protected function isFailedProviderResponse(array $balance): bool
    {
        $raw = $balance['raw'] ?? null;
        $asOf = $balance['as_of'] ?? null;

        return $asOf === null
            && (is_array($raw) && $raw === [])
            && $this->normalizeMoneyToMinor($balance['balance'] ?? 0) === 0
            && $this->normalizeMoneyToMinor($balance['available_balance'] ?? 0) === 0;
    }

    protected function message(bool $ready, ?int $requiredMinor): string
    {
        if ($requiredMinor === null) {
            return 'NetBank source account balance was refreshed.';
        }

        return $ready
            ? 'NetBank source account has enough available balance.'
            : 'NetBank source account cannot cover the requested amount.';
    }

    protected function maskAccountNumber(string $accountNumber): string
    {
        $length = strlen($accountNumber);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($accountNumber, -4);
    }
}
