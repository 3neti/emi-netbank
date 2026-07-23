<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use DateTimeImmutable;

final readonly class NetbankReusableFundingObservationData
{
    public function __construct(
        public string $transactionHash,
        public string $payloadHash,
        public int $grossAmountMinor,
        public int $feeAmountMinor,
        public int $netAmountMinor,
        public string $currency,
        public string $providerStatus,
        public ?DateTimeImmutable $occurredAt,
        public ?DateTimeImmutable $settledAt,
    ) {}
}
