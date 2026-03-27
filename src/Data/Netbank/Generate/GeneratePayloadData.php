<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Generate;

use Brick\Money\Money;
use LBHurtado\Merchant\Contracts\MerchantInterface;
use LBHurtado\PaymentGateway\Data\Netbank\Common\PayloadAmountData;
use Spatie\LaravelData\Data;

class GeneratePayloadData extends Data
{
    public function __construct(
        public string $merchant_name,
        public string $merchant_city,
        public string $qr_type,
        public string $qr_transaction_type,
        public string $destination_account,
        public int $resolution,
        public PayloadAmountData $amount,
    ) {}

    public static function fromUserAccountAmount(MerchantInterface $user, string $account, Money $amount, bool $shouldFormatDestination = false): self
    {
        return new self(
            merchant_name: $user->merchant->name,
            merchant_city: $user->merchant->city,
            qr_type: $amount->isZero() ? 'Static' : 'Dynamic',
            qr_transaction_type: 'P2M',
            destination_account: $shouldFormatDestination
                ? self::formatDestinationAccount($account, $user->merchant->code)
                : self::concatenateDestinationAccount($account),
            resolution: 480,
            amount: PayloadAmountData::fromMoney($amount),
        );
    }

    protected static function concatenateDestinationAccount(string $account): string
    {
        return __(':alias:account', [
            'alias' => config('disbursement.client.alias'),
            'account' => $account,
        ]);
    }

    protected static function formatDestinationAccount(string $account, ?string $merchantCode): string
    {
        return __(':alias:account', [
            'alias' => config('disbursement.client.alias'),
            'account' => $merchantCode ? $merchantCode[0].substr($account, 1) : $account,
        ]);
    }
}
