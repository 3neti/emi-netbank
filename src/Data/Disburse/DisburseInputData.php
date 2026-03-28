<?php

namespace LBHurtado\PaymentGateway\Data\Disburse;

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
        public string $via,
        public ?int $voucher_id = null,
        public ?string $voucher_code = null,
        public ?int $user_id = null,
        public ?string $mobile = null,
    ) {
        // Sanitize account number: strip all non-numeric characters
        // Handles cases like '0 917 301 1987', '0917-301-1987', '+639173011987'
        $this->account_number = preg_replace('/[^0-9]/', '', $account_number);
    }

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
