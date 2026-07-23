<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use LBHurtado\PaymentGateway\Enums\NetbankStandingAddressScheme;

final readonly class NetbankStandingAddressReferenceData
{
    public function __construct(
        public string $reference,
        public NetbankStandingAddressScheme $scheme,
        public ?string $keyId,
        public int $counter,
    ) {}
}
