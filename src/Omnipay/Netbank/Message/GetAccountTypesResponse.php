<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

class GetAccountTypesResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return isset($this->data['result']) && ! isset($this->data['error']);
    }

    public function getAccountTypes(): array
    {
        return $this->data['result'] ?? [];
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
