<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\DirectCheckout;

use Spatie\LaravelData\Data;

class CollectionRequestData extends Data
{
    public function __construct(
        public string $reference_no,
        public int $amount,
        public string $currency = 'PHP',
        public ?string $institution_code = null,
        public ?array $customer = null,
    ) {}

    public function toPayload(): array
    {
        $payload = [
            'access_key' => config('payment-gateway.netbank.direct_checkout.access_key'),
            'secret_key' => config('payment-gateway.netbank.direct_checkout.secret_key'),
            'reference_no' => $this->reference_no,
            'amount' => [
                'cur' => $this->currency,
                'num' => (string) $this->amount,
            ],
        ];

        if ($this->institution_code) {
            $payload['institution_code'] = $this->institution_code;
        }

        if ($this->customer) {
            $payload['customer'] = $this->customer;
        }

        return $payload;
    }
}
