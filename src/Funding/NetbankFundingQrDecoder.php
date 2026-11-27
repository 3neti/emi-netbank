<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use LBHurtado\EmiCore\Contracts\FundingQrCodeDecoder;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\EmiCore\Data\Funding\FundingQrDecodeResultData;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;

final class NetbankFundingQrDecoder implements FundingQrCodeDecoder
{
    public function __construct(
        private readonly NetbankFundingApiClient $client,
        private readonly NetbankQrImageDecoder $imageDecoder,
    ) {}

    public function decode(FundingQrCodeData $qrCode): FundingQrDecodeResultData
    {
        if (strtolower(trim($qrCode->mimeType)) !== 'image/png') {
            throw NetbankFundingRequestFailed::invalidResponse(
                'decode-qrph-image',
            );
        }

        $qrString = $this->imageDecoder->decode($qrCode->base64Payload);

        return new FundingQrDecodeResultData(
            qrString: $qrString,
            providerData: $this->client->decodeQrString($qrString),
        );
    }
}
