<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;

final readonly class NetbankReusableFundingAddressData
{
    public function __construct(
        public string $provider,
        public string $fundingAddress,
        public string $currency,
        public string $institution,
        public string $merchantName,
        public FundingQrCodeData $qrCode,
    ) {}
}
