<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * NetBank Generate QR Response
 *
 * Response from NetBank QR generation API.
 *
 * Example response data:
 * <code>
 * {
 *   "status": "success",
 *   "data": {
 *     "qr_code": "00020101021226370011com.netbank01090123456780211QR123456780303PHP5204000053030...",
 *     "qr_url": "https://api.netbank.example.com/qr/view/abc123",
 *     "qr_id": "qr_abc123xyz",
 *     "expires_at": "2024-12-31T23:59:59Z"
 *   }
 * }
 * </code>
 */
class GenerateQrResponse extends AbstractResponse
{
    /**
     * Check if the request was successful
     */
    public function isSuccessful(): bool
    {
        // NetBank returns {"qr_code": "base64data..."} directly
        return isset($this->data['qr_code']) && ! isset($this->data['error']);
    }

    /**
     * Get the QR code string (base64 PNG image from NetBank)
     */
    public function getQrCode(): ?string
    {
        // NetBank returns base64 PNG, prepend data URI scheme
        $qrCode = $this->data['qr_code'] ?? null;

        return $qrCode ? 'data:image/png;base64,'.$qrCode : null;
    }

    /**
     * Get the QR code URL (for displaying or sharing)
     */
    public function getQrUrl(): ?string
    {
        return $this->data['data']['qr_url'] ?? null;
    }

    /**
     * Get the QR ID from the provider
     */
    public function getQrId(): ?string
    {
        return $this->data['data']['qr_id'] ?? null;
    }

    /**
     * Get the expiration timestamp
     */
    public function getExpiresAt(): ?string
    {
        return $this->data['data']['expires_at'] ?? null;
    }

    /**
     * Get the error message if request failed
     */
    public function getMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->data['message']
            ?? $this->data['error']
            ?? 'Unknown error occurred';
    }

    /**
     * Get the error code if request failed
     */
    public function getCode(): ?string
    {
        return $this->data['code'] ?? null;
    }
}
