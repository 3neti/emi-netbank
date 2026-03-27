<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Deposit;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class DepositResponseData extends Data
{
    public function __construct(
        public string $alias,
        public int $amount,
        public string $channel,
        public int $commandId,
        public string $externalTransferStatus,
        public int $operationId,
        public string $productBranchCode,
        public string $recipientAccountNumber,
        public string $recipientAccountNumberBankFormat,
        public string $referenceCode,
        public string $referenceNumber,
        public string $registrationTime,
        public string $remarks,
        public DepositSenderData $sender,
        public string $transferType,
        public DepositMerchantDetailsData|Optional $merchant_details
    ) {}
}
