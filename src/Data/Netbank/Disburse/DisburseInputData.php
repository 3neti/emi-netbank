<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

use Illuminate\Validation\Rule;
use LBHurtado\PaymentGateway\Data\SettlementBanksData;
use Spatie\LaravelData\Data;

class DisburseInputData extends Data
{
    public function __construct(
        public string $reference,
        public int|float $amount,
        public string $account_number,
        public string $bank,
        public string $via
    ) {}

    public static function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'min:2'],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'account_number' => ['required', 'string'],
            'bank' => ['required', 'string', Rule::in(SettlementBanksData::indices())],
            'via' => ['required', 'string', 'in:'.implode(',', config('disbursement.settlement_rails', []))],
        ];
    }
}
