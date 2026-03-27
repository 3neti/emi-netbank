<?php

namespace LBHurtado\PaymentGateway\Data\Disburse;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\PaymentGateway\Data\SettlementBanksData;
use LBHurtado\Voucher\Models\Voucher;
use Spatie\LaravelData\Data;

class DisburseInputData extends Data
{
    /** @var bool Enable verbose data flow logging */
    private const DEBUG = false;

    const BANK_ACCOUNT_KEY = 'redemption.bank_account';

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

    public static function fromVoucher(
        Voucher $voucher,
        ?string $via = null,
        ?float $amount = null,
        ?int $sliceNumber = null,
    ): self {
        if (self::DEBUG) {
            Log::debug('[DisburseInputData] fromVoucher beginning', [
                'voucher_code' => $voucher->code,
            ]);
        }

        $cash = $voucher->cash;
        if (! $cash) {
            Log::error('[DisburseInputData] No Cash entity found', ['voucher' => $voucher->code]);
            throw new \RuntimeException("Voucher {$voucher->code} has no cash entity");
        }
        if (self::DEBUG) {
            Log::debug('[DisburseInputData] Found Cash entity', [
                'cash_id' => $cash->getKey(),
                'amount' => $cash->amount->getAmount()->toFloat(),
            ]);
        }

        $redeemer = $voucher->redeemer;
        if (! $redeemer) {
            Log::error('[DisburseInputData] No Redeemer relation found', ['voucher' => $voucher->code]);
            throw new \RuntimeException("Voucher {$voucher->code} has no redeemer");
        }
        if (self::DEBUG) {
            Log::debug('[DisburseInputData] Found Redeemer relation', [
                'redeemer_id' => $redeemer->getKey(),
            ]);
        }

        $contact = $voucher->contact;
        if (! $contact) {
            Log::error('[DisburseInputData] No Contact attached to voucher', ['voucher' => $voucher->code]);
            throw new \RuntimeException("Voucher {$voucher->code} has no Contact attached");
        }
        if (self::DEBUG) {
            Log::debug('[DisburseInputData] Found Contact', [
                'contact_id' => $contact->getKey(),
                'contact_mobile' => $contact->mobile,
            ]);
        }

        $rawBank = Arr::get($redeemer->metadata, self::BANK_ACCOUNT_KEY, $contact->bank_account);
        if (self::DEBUG) {
            Log::debug('[DisburseInputData] Raw bank value from metadata or fallback', [
                'metadata' => $redeemer->metadata,
                'key' => self::BANK_ACCOUNT_KEY,
                'rawBank' => $rawBank,
                'fallbackBank' => $contact->bank_account,
            ]);
        }

        $bankAccount = BankAccount::fromBankAccountWithFallback($rawBank, $contact->bank_account);
        if (self::DEBUG) {
            Log::debug('[DisburseInputData] Parsed BankAccount', [
                'bank_code' => $bankAccount->getBankCode(),
                'account_number' => $bankAccount->getAccountNumber(),
            ]);
        }

        $baseReference = "{$voucher->code}-{$contact->mobile}";
        $reference = $sliceNumber !== null ? "{$baseReference}-S{$sliceNumber}" : $baseReference;
        $amount = $amount ?? $cash->amount->getAmount()->toFloat();
        $account = $bankAccount->getAccountNumber();
        $bank = $bankAccount->getBankCode();

        // Smart rail selection: Use voucher instruction, parameter, or auto-select based on amount
        if ($via === null) {
            // Check if voucher has settlement_rail in instructions
            $settlementRailEnum = $voucher->instructions?->cash?->settlement_rail ?? null;

            if ($settlementRailEnum instanceof \LBHurtado\PaymentGateway\Enums\SettlementRail) {
                $via = $settlementRailEnum->value;
                if (self::DEBUG) {
                    Log::debug('[DisburseInputData] Using rail from voucher instructions', ['rail' => $via]);
                }
            } else {
                // Auto-select based on amount: INSTAPAY <50k, PESONET ≥50k
                $via = $amount < 50000 ? 'INSTAPAY' : 'PESONET';
                if (self::DEBUG) {
                    Log::debug('[DisburseInputData] Auto-selected rail based on amount', [
                        'amount' => $amount,
                        'selected_rail' => $via,
                    ]);
                }
            }
        }

        if (self::DEBUG) {
            Log::debug('[DisburseInputData] Building final payload', compact('reference', 'amount', 'account', 'bank', 'via'));
        }

        return self::from([
            'reference' => $reference,
            'amount' => $amount,
            'account_number' => $account,
            'bank' => $bank,
            'via' => $via,
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'user_id' => $voucher->user_id,
            'mobile' => $contact->mobile,
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
