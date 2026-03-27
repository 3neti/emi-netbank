<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\PaymentGateway\Data\SettlementBanksData;
use LBHurtado\Voucher\Models\Voucher;
use Spatie\LaravelData\Data;

class DisburseInputData extends Data
{
    const BANK_ACCOUNT_KEY = 'redemption.bank_account';

    public function __construct(
        public string $reference,
        public int|float $amount,
        public string $account_number,
        public string $bank,
        public string $via
    ) {}

    public static function fromVoucher(
        Voucher $voucher,
        string $via = 'INSTAPAY',
    ): self {
        Log::debug('[DisburseInputData] fromVoucher beginning', [
            'voucher_code' => $voucher->code,
        ]);

        $cash = $voucher->cash;
        if (! $cash) {
            Log::error('[DisburseInputData] No Cash entity found', ['voucher' => $voucher->code]);
            throw new \RuntimeException("Voucher {$voucher->code} has no cash entity");
        }
        Log::debug('[DisburseInputData] Found Cash entity', [
            'cash_id' => $cash->getKey(),
            'amount' => $cash->amount->getAmount()->toFloat(),
        ]);

        $redeemer = $voucher->redeemer;
        if (! $redeemer) {
            Log::error('[DisburseInputData] No Redeemer relation found', ['voucher' => $voucher->code]);
            throw new \RuntimeException("Voucher {$voucher->code} has no redeemer");
        }
        Log::debug('[DisburseInputData] Found Redeemer relation', [
            'redeemer_id' => $redeemer->getKey(),
        ]);

        $contact = $voucher->contact;
        if (! $contact) {
            Log::error('[DisburseInputData] No Contact attached to voucher', ['voucher' => $voucher->code]);
            throw new \RuntimeException("Voucher {$voucher->code} has no Contact attached");
        }
        Log::debug('[DisburseInputData] Found Contact', [
            'contact_id' => $contact->getKey(),
            'contact_mobile' => $contact->mobile,
        ]);

        $rawBank = Arr::get($redeemer->metadata, self::BANK_ACCOUNT_KEY, $contact->bank_account);
        Log::debug('[DisburseInputData] Raw bank value from metadata or fallback', [
            'metadata' => $redeemer->metadatak,
            'key' => self::BANK_ACCOUNT_KEY,
            'rawBank' => $rawBank,
            'fallbackBank' => $contact->bank_account,
        ]);

        $bankAccount = BankAccount::fromBankAccountWithFallback($rawBank, $contact->bank_account);
        Log::debug('[DisburseInputData] Parsed BankAccount', [
            'bank_code' => $bankAccount->getBankCode(),
            'account_number' => $bankAccount->getAccountNumber(),
        ]);

        $reference = "{$voucher->code}-{$contact->mobile}";
        $amount = $cash->amount->getAmount()->toFloat();
        $account = $bankAccount->getAccountNumber();
        $bank = $bankAccount->getBankCode();

        Log::debug('[DisburseInputData] Building final payload', compact('reference', 'amount', 'account', 'bank', 'via'));

        return self::from([
            'reference' => $reference,
            'amount' => $amount,
            'account_number' => $account,
            'bank' => $bank,
            'via' => $via,
        ]);
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
