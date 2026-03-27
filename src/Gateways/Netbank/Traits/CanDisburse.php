<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transaction;
use Brick\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisbursePayloadData;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Wallet\Events\DisbursementConfirmed;

trait CanDisburse
{
    /**
     * Attempt to disburse funds.
     */
    public function disburse(Wallet $wallet, DisburseInputData|array $validated): DisburseResponseData|bool
    {
        $data = $validated instanceof DisburseInputData
            ? $validated->toArray()
            : $validated;

        $amount = Arr::get($data, 'amount');
        $currency = config('disbursement.currency', 'PHP');
        $credits = Money::of($amount, $currency);

        DB::beginTransaction();

        try {
            // Transfer funds from system wallet to user wallet
            $transfer = TopupWalletAction::run($wallet, $amount);

            // Get the deposit transaction (user receiving)
            $transaction = $transfer->deposit;

            // Build and log request payload
            $payload = DisbursePayloadData::fromValidated($data)->toArray();
            Log::info('[Netbank] Disburse payload prepared', $payload);

            // Send to bank
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->post(config('disbursement.server.end-point'), $payload);

            if (! $response->successful()) {
                Log::warning('[Netbank] Disbursement failed', ['body' => $response->body()]);
                DB::rollBack();

                return false;
            }

            // Persist operationId and commit
            $transaction->meta = [
                'operationId' => $response->json('transaction_id'),
                'user_id' => $wallet->getKey(),
                'payload' => $payload,
            ];
            $transaction->save();

            DB::commit();

            // Build response DTO
            return DisburseResponseData::from(array_merge(
                ['uuid' => $transaction->uuid],
                $response->json()
            ));
        } catch (\Throwable $e) {
            Log::error('[Netbank] Disbursement error', ['error' => $e->getMessage()]);
            DB::rollBack();

            return false;
        }
    }

    /**
     * Retrieve the validation rules for disbursement input.
     */
    protected function rules(): array
    {
        $min = config('disbursement.min');
        $max = config('disbursement.max');
        $rails = config('disbursement.settlement_rails', []);

        return [
            'reference' => ['required', 'string', 'min:2', 'unique:references,code'],
            'bank' => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'via' => ['required', 'string', Rule::in($rails)],
            'amount' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
        ];
    }

    /**
     * Confirm a previously‐reserved disbursement once the bank calls back.
     */
    public function confirmDisbursement(string $operationId): bool
    {
        try {
            $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)
                ->firstOrFail();

            // Mark it as completed
            $transaction->payable->confirm($transaction);
            DisbursementConfirmed::dispatch($transaction);

            Log::info("[Netbank] Disbursement confirmed for operation {$operationId}");

            return true;
        } catch (\Throwable $e) {
            Log::error('[Netbank] Confirm disbursement failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Check the status of a disbursement transaction.
     *
     * @param  string  $transactionId  Gateway transaction ID
     * @return array{status: string, raw: array, error?: string}
     */
    public function checkDisbursementStatus(string $transactionId): array
    {
        try {
            $endpoint = config('disbursement.server.status-endpoint');

            if (empty($endpoint)) {
                Log::error('[Netbank] Status endpoint not configured', [
                    'transaction_id' => $transactionId,
                    'config_key' => 'disbursement.server.status-endpoint',
                ]);

                return ['status' => 'error', 'raw' => [], 'error' => 'Status endpoint not configured (NETBANK_STATUS_ENDPOINT missing)'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->get($endpoint.'/'.$transactionId);

            if (! $response->successful()) {
                Log::warning('[Netbank] Status check failed', [
                    'transaction_id' => $transactionId,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);

                return ['status' => 'error', 'raw' => [], 'error' => 'Gateway returned HTTP '.$response->status()];
            }

            $data = $response->json();
            $rawStatus = $data['status'] ?? 'Pending';
            $normalized = \LBHurtado\PaymentGateway\Enums\DisbursementStatus::fromGateway('netbank', $rawStatus);

            Log::info('[Netbank] Status checked', [
                'transaction_id' => $transactionId,
                'raw_status' => $rawStatus,
                'normalized_status' => $normalized->value,
            ]);

            return [
                'status' => $normalized->value,
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('[Netbank] Status check error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['status' => 'error', 'raw' => [], 'error' => 'Status check failed: '.$e->getMessage()];
        }
    }
}
