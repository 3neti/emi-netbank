<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

use Illuminate\Support\Arr;
use LBHurtado\PaymentGateway\Data\Netbank\Common\PayloadAmountData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class DisbursePayloadData extends Data
{
    public function __construct(
        #[MapInputName('reference')]
        public string $reference_id,
        #[MapInputName('via')]
        public string $settlement_rail,
        public PayloadAmountData $amount,
        public string $source_account_number,
        public array $sender,
        public DisbursePayloadDestinationAccountData $destination_account,
        public DisbursePayloadRecipientData $recipient,
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new DisbursePayloadData(
            reference_id: Arr::get($validated, 'reference'),
            settlement_rail: Arr::get($validated, 'via'),
            amount: PayloadAmountData::from($validated),
            source_account_number: config('disbursement.source.account_number'),
            sender: config('disbursement.source.sender'),
            destination_account: DisbursePayloadDestinationAccountData::from($validated),
            recipient: DisbursePayloadRecipientData::from($validated),
        );
    }
}
