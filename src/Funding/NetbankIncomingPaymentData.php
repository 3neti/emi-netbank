<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use DateTimeImmutable;

/** Provider-reported incoming credit; payer details are not verified contact details. */
final readonly class NetbankIncomingPaymentData
{
    public function __construct(
        public string $transactionId,
        public int $amountMinor,
        public string $currency,
        public string $status,
        public ?DateTimeImmutable $occurredAt,
        public ?DateTimeImmutable $settledAt,
        public ?string $referenceId,
        public ?string $settlementRail,
        public ?string $payerName,
        public ?string $payerAccountNumber,
        public ?string $payerInstitutionCode,
        public ?string $payerMobile,
    ) {}
}
