<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * Create Customer Response (Account-As-A-Service)
 */
class CreateCustomerResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return isset($this->data['customer_id']) && ! isset($this->data['error']);
    }

    public function getCustomerId(): ?string
    {
        return $this->data['customer_id'] ?? null;
    }

    public function getMessage(): ?string
    {
        if (isset($this->data['error'])) {
            return $this->data['error'];
        }

        if (isset($this->data['message'])) {
            return $this->data['message'];
        }

        return null;
    }

    public function getCode(): ?int
    {
        return $this->data['status_code'] ?? null;
    }
}
