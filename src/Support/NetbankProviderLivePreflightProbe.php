<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\ProviderLivePreflightProbe;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderLivePreflightRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderLivePreflightResultData;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\EmiCore\Exceptions\ProviderLivePreflightFailed;
use Throwable;

final readonly class NetbankProviderLivePreflightProbe implements ProviderLivePreflightProbe
{
    private const Provider = 'netbank';

    public function __construct(
        private NetbankProviderBalanceReader $balanceReader,
    ) {}

    public function providerCode(): string
    {
        return self::Provider;
    }

    public function checkLiveReadiness(
        ProviderLivePreflightRequestData $request,
    ): ProviderLivePreflightResultData {
        if (mb_strtolower(trim($request->provider)) !== self::Provider) {
            throw new InvalidArgumentException('NetBank live preflight provider mismatch.');
        }

        try {
            $observation = $this->balanceReader->readBalance(
                new ProviderBalanceRequestData(
                    provider: $request->provider,
                    connectionReference: $request->connectionReference,
                    settlementResourceReference: $request->settlementResourceReference,
                    currency: $request->currency,
                ),
            );

            return new ProviderLivePreflightResultData(
                provider: self::Provider,
                connectionReference: $request->connectionReference,
                ready: true,
                checkedAt: $observation->observedAt,
                observation: $observation,
            );
        } catch (ProviderLivePreflightFailed $exception) {
            $failureCode = $exception->failureCode;
        } catch (Throwable) {
            $failureCode = ProviderLivePreflightFailureCode::ProviderUnavailable;
        }

        return new ProviderLivePreflightResultData(
            provider: self::Provider,
            connectionReference: $request->connectionReference,
            ready: false,
            checkedAt: DateTimeImmutable::createFromInterface(now()),
            failureCode: $failureCode,
        );
    }
}
