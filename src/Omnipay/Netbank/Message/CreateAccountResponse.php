<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

class CreateAccountResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return isset($this->data['account_number']) && ! isset($this->data['error']);
    }

    public function getAccountNumber(): ?string
    {
        return $this->data['account_number'] ?? null;
    }

    public function getMessage(): ?string
    {
        return $this->data['error'] ?? $this->data['message'] ?? null;
    }

    public function getCode(): ?int
    {
        return $this->data['status_code'] ?? null;
    }
}
