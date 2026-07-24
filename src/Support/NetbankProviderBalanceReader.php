<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\ProviderBalanceReader;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceObservationData;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use RuntimeException;
use Throwable;

final readonly class NetbankProviderBalanceReader implements ProviderBalanceReader
{
    private const Provider = 'netbank';

    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    public function providerCode(): string
    {
        return self::Provider;
    }

    public function readBalance(
        ProviderBalanceRequestData $request,
    ): ProviderBalanceObservationData {
        if (mb_strtolower(trim($request->provider)) !== self::Provider) {
            throw new InvalidArgumentException('NetBank balance provider mismatch.');
        }

        $accountNumber = trim((string) config(
            'payment-gateway.netbank.funding.corporate_account_number',
        ));

        if ($accountNumber === '') {
            throw new RuntimeException('NetBank corporate account is not configured.');
        }

        $result = $this->gateway->checkAccountBalance($accountNumber);
        $currency = mb_strtoupper(trim((string) ($result['currency'] ?? '')));
        $raw = (array) ($result['raw'] ?? []);

        if ($raw === [] || $currency !== mb_strtoupper(trim($request->currency))) {
            throw new RuntimeException('NetBank did not return a valid balance observation.');
        }

        $amountMinor = filter_var(
            $result['available_balance'] ?? null,
            FILTER_VALIDATE_INT,
        );

        if ($amountMinor === false || $amountMinor < 0) {
            throw new RuntimeException('NetBank returned an invalid available balance.');
        }

        $observedAt = $this->observedAt($result['as_of'] ?? null);
        $evidenceReference = 'netbank-balance:'.hash('sha256', implode('|', [
            $request->connectionReference,
            $request->settlementResourceReference,
            $currency,
            (string) $amountMinor,
            $observedAt->format(DATE_ATOM),
        ]));

        return new ProviderBalanceObservationData(
            provider: self::Provider,
            connectionReference: $request->connectionReference,
            settlementResourceReference: $request->settlementResourceReference,
            amountMinor: $amountMinor,
            currency: $currency,
            observedAt: $observedAt,
            evidenceReference: $evidenceReference,
        );
    }

    private function observedAt(mixed $value): DateTimeImmutable
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (Throwable) {
                throw new RuntimeException('NetBank returned an invalid balance timestamp.');
            }
        }

        return DateTimeImmutable::createFromInterface(now());
    }
}
