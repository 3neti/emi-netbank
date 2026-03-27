<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * NetBank Disburse Response
 *
 * Response object for disbursement operations via NetBank gateway.
 */
class DisburseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return ! isset($this->data['error'])
            && isset($this->data['transaction_id']);
    }

    public function getMessage(): ?string
    {
        return $this->data['message'] ?? $this->data['error_message'] ?? null;
    }

    public function getCode(): ?string
    {
        return $this->data['status_code'] ?? $this->data['error_code'] ?? null;
    }

    public function getTransactionReference(): ?string
    {
        return $this->getOperationId();
    }

    /**
     * Get the NetBank operation ID (transaction ID)
     */
    public function getOperationId(): ?string
    {
        return $this->data['transaction_id'] ?? null;
    }

    /**
     * Get the transaction status
     */
    public function getStatus(): ?string
    {
        return $this->data['status'] ?? null;
    }

    /**
     * Get the transaction UUID (for mapping back to Transaction model)
     */
    public function getTransactionUuid(): ?string
    {
        return $this->data['uuid'] ?? null;
    }

    /**
     * Check if this is an error response
     */
    public function isError(): bool
    {
        return isset($this->data['error']) || isset($this->data['error_message']);
    }
}
