<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use Zxing\QrReader;

class NetbankQrImageDecoder
{
    public function decode(string $base64Png): string
    {
        $encoded = trim($base64Png);
        $decoded = base64_decode($encoded, true);

        if (
            $encoded === ''
            || $decoded === false
            || ! str_starts_with($decoded, "\x89PNG\r\n\x1a\n")
            || strlen($decoded) > (int) config(
                'payment-gateway.netbank.funding.qr_decode_max_bytes',
                5 * 1024 * 1024,
            )
            || ! is_array(@getimagesizefromstring($decoded))
        ) {
            throw NetbankFundingRequestFailed::invalidResponse('decode-qrph-image');
        }

        $path = tempnam(sys_get_temp_dir(), 'emi-netbank-qr-');

        if ($path === false || file_put_contents($path, $decoded) === false) {
            if ($path !== false) {
                @unlink($path);
            }

            throw NetbankFundingRequestFailed::invalidResponse('decode-qrph-image');
        }

        try {
            $value = (new QrReader($path))->text();
        } catch (\Throwable) {
            throw NetbankFundingRequestFailed::invalidResponse('decode-qrph-image');
        } finally {
            @unlink($path);
        }

        if (! is_string($value) || trim($value) === '') {
            throw NetbankFundingRequestFailed::invalidResponse('decode-qrph-image');
        }

        return trim($value);
    }
}
