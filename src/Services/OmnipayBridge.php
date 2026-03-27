<?php

namespace LBHurtado\PaymentGateway\Services;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transaction;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Merchant\Contracts\MerchantInterface;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Data\Wallet\BalanceData;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\Wallet\Events\DisbursementConfirmed;
use Omnipay\Common\GatewayInterface;

/**
 * Bridge between Omnipay gateways and PaymentGatewayInterface
 *
 * Adapts Omnipay's gateway pattern to work with the existing
 * payment-gateway interface, adding settlement rail validation
 * and EMI support.
 */
class OmnipayBridge implements PaymentGatewayInterface
{
    protected GatewayInterface $gateway;

    protected BankRegistry $bankRegistry;

    public function __construct(GatewayInterface $gateway)
    {
        $this->gateway = $gateway;
        $this->bankRegistry = app(BankRegistry::class);
    }

    /**
     * Generate QR code for payment
     *
     * @param  string  $account  Account number
     * @param  Money  $amount  Amount to generate QR for
     * @return string QR code data
     */
    public function generate(string $account, Money $amount): string
    {
        $user = auth()->user();

        if (! $user instanceof MerchantInterface) {
            throw new \LogicException('User must implement MerchantInterface');
        }

        // Build cache key
        $amountKey = (string) $amount;
        $currency = $amount->getCurrency()->getCurrencyCode();
        $userKey = $user->getKey();
        $cacheKey = "qr:merchant:{$userKey}:{$account}:{$currency}_{$amountKey}";

        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($user, $account, $amount) {
            $response = $this->gateway->generateQr([
                'accountNumber' => $account,
                'amount' => $amount->getMinorAmount()->toInt(),
                'merchantId' => $user->getMerchant()->id,
                'currency' => $amount->getCurrency()->getCurrencyCode(),
                'reference' => 'QR-'.uniqid(),
            ])->send();

            if (! $response->isSuccessful()) {
                Log::error('[OmnipayBridge] QR generation failed', [
                    'message' => $response->getMessage(),
                ]);
                throw new \RuntimeException('Failed to generate QR code: '.$response->getMessage());
            }

            return $response->getQrCode();
        });
    }

    /**
     * Disburse funds to a recipient
     *
     * @param  Wallet  $wallet  User wallet
     * @param  DisburseInputData|array  $validated  Disbursement data
     * @return DisburseResponseData|bool Response data or false on failure
     */
    public function disburse(Wallet $wallet, DisburseInputData|array $validated): DisburseResponseData|bool
    {
        $data = $validated instanceof DisburseInputData
            ? $validated->toArray()
            : $validated;

        $amount = $data['amount'];
        $currency = config('disbursement.currency', 'PHP');
        $credits = Money::of($amount, $currency);

        // Parse and validate settlement rail
        $rail = SettlementRail::from($data['via']);
        $this->validateBankSupportsRail($data['bank'], $rail);

        DB::beginTransaction();

        try {
            // Reserve funds (not confirmed yet)
            $transaction = $wallet->withdraw(
                $credits->getMinorAmount()->toInt(),
                [],
                false // not confirmed
            );

            // Convert amount to minor units (centavos)
            $amountInCentavos = (int) ($amount * 100);

            // Call Omnipay gateway
            $response = $this->gateway->disburse([
                'amount' => $amountInCentavos,
                'accountNumber' => $data['account_number'],
                'bankCode' => $data['bank'],
                'reference' => $data['reference'],
                'via' => $data['via'],
                'currency' => $currency,
            ])->send();

            if (! $response->isSuccessful()) {
                Log::warning('[OmnipayBridge] Disbursement failed', [
                    'message' => $response->getMessage(),
                    'code' => $response->getCode(),
                    'rail' => $rail->value,
                    'bank' => $data['bank'],
                    'reference' => $data['reference'],
                ]);
                DB::rollBack();

                return false;
            }

            // Store operation ID and rail info in transaction meta
            $transaction->meta = [
                'operationId' => $response->getOperationId(),
                'user_id' => $wallet->getKey(),
                'payload' => $data,
                'settlement_rail' => $rail->value,
                'bank_code' => $data['bank'],
                'is_emi' => $this->bankRegistry->isEMI($data['bank']),
            ];
            $transaction->save();

            DB::commit();

            Log::info('[OmnipayBridge] Disbursement initiated', [
                'transaction_uuid' => $transaction->uuid,
                'operation_id' => $response->getOperationId(),
                'rail' => $rail->value,
                'bank' => $data['bank'],
                'amount' => $amount,
            ]);

            // Return response DTO
            return DisburseResponseData::from([
                'uuid' => $transaction->uuid,
                'transaction_id' => $response->getOperationId(),
                'status' => $response->getStatus(),
            ]);

        } catch (\Throwable $e) {
            Log::error('[OmnipayBridge] Disbursement error', [
                'error' => $e->getMessage(),
                'rail' => $rail->value ?? null,
                'bank' => $data['bank'] ?? null,
            ]);
            DB::rollBack();

            return false;
        }
    }

    /**
     * Confirm a deposit transaction
     *
     * @param  array  $payload  Webhook payload
     * @return bool Success status
     */
    public function confirmDeposit(array $payload): bool
    {
        // This would be implemented based on your webhook logic
        // For now, just log and return true
        Log::info('[OmnipayBridge] Deposit confirmation received', [
            'payload' => $payload,
        ]);

        return true;
    }

    /**
     * Confirm a disbursement operation
     *
     * @param  string  $operationId  Gateway operation ID
     * @return bool Success status
     */
    public function confirmDisbursement(string $operationId): bool
    {
        try {
            $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)
                ->firstOrFail();

            // Confirm the transaction
            $transaction->payable->confirm($transaction);

            // Dispatch event
            DisbursementConfirmed::dispatch($transaction);

            $rail = $transaction->meta['settlement_rail'] ?? 'unknown';
            $bank = $transaction->meta['bank_code'] ?? 'unknown';

            Log::info('[OmnipayBridge] Disbursement confirmed', [
                'operation_id' => $operationId,
                'transaction_uuid' => $transaction->uuid,
                'rail' => $rail,
                'bank' => $bank,
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('[OmnipayBridge] Confirm disbursement failed', [
                'operation_id' => $operationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check gateway balance
     *
     * @return BalanceData Balance information
     */
    public function checkBalance(): BalanceData
    {
        $response = $this->gateway->checkBalance([
            'accountNumber' => config('disbursement.account_number'),
        ])->send();

        if (! $response->isSuccessful()) {
            throw new \RuntimeException('Failed to check balance: '.$response->getMessage());
        }

        return new BalanceData(
            amount: $response->getBalance(),
            currency: $response->getCurrency(),
        );
    }

    /**
     * Check the status of a disbursement transaction
     *
     * @param  string  $transactionId  Gateway transaction ID
     * @return array{status: string, raw: array, error?: string} Normalized status + raw response
     */
    public function checkDisbursementStatus(string $transactionId): array
    {
        try {
            $response = $this->gateway
                ->checkDisbursementStatus([
                    'transactionId' => $transactionId,
                ])
                ->send();

            if (! $response->isSuccessful()) {
                $errorMessage = $response->getMessage() ?? 'Unknown gateway error';

                Log::warning('[OmnipayBridge] Status check failed', [
                    'transaction_id' => $transactionId,
                    'message' => $errorMessage,
                ]);

                return ['status' => 'error', 'raw' => [], 'error' => 'Gateway returned error: '.$errorMessage];
            }

            $rawStatus = $response->getStatus();
            $normalized = \LBHurtado\PaymentGateway\Enums\DisbursementStatus::fromGateway('netbank', $rawStatus);

            Log::info('[OmnipayBridge] Status checked', [
                'transaction_id' => $transactionId,
                'raw_status' => $rawStatus,
                'normalized_status' => $normalized->value,
            ]);

            return [
                'status' => $normalized->value,
                'raw' => $response->getRawData(),
            ];
        } catch (\Throwable $e) {
            Log::error('[OmnipayBridge] Status check error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['status' => 'error', 'raw' => [], 'error' => 'Status check failed: '.$e->getMessage()];
        }
    }

    /**
     * Check account balance (PaymentGatewayInterface implementation).
     *
     * @param  string  $accountNumber  Account number to check
     * @return array{balance: int, available_balance: int, currency: string, as_of: ?string, raw: array}
     */
    public function checkAccountBalance(string $accountNumber): array
    {
        try {
            Log::debug('[OmnipayBridge] Checking balance', [
                'account' => $accountNumber,
            ]);

            $response = $this->gateway->checkBalance([
                'accountNumber' => $accountNumber,
            ])->send();

            if (! $response->isSuccessful()) {
                Log::warning('[OmnipayBridge] Balance check failed', [
                    'account' => $accountNumber,
                    'error' => $response->getMessage(),
                ]);

                return [
                    'balance' => 0,
                    'available_balance' => 0,
                    'currency' => 'PHP',
                    'as_of' => null,
                    'raw' => [],
                ];
            }

            Log::info('[OmnipayBridge] Balance checked', [
                'account' => $accountNumber,
                'balance' => $response->getBalance(),
            ]);

            return [
                'balance' => $response->getBalance(),
                'available_balance' => $response->getAvailableBalance(),
                'currency' => $response->getCurrency(),
                'as_of' => $response->getAsOf(),
                'raw' => $response->getData(),
            ];

        } catch (\Throwable $e) {
            Log::error('[OmnipayBridge] Balance check error', [
                'account' => $accountNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'balance' => 0,
                'available_balance' => 0,
                'currency' => 'PHP',
                'as_of' => null,
                'raw' => [],
            ];
        }
    }

    /**
     * Validate that bank supports the selected settlement rail
     *
     * @param  string  $bankCode  SWIFT BIC code
     * @param  SettlementRail  $rail  Settlement rail
     *
     * @throws \InvalidArgumentException If bank doesn't support rail
     */
    protected function validateBankSupportsRail(string $bankCode, SettlementRail $rail): void
    {
        if (! $this->bankRegistry->supportsRail($bankCode, $rail)) {
            throw new \InvalidArgumentException(
                "Bank {$bankCode} does not support {$rail->value} settlement rail"
            );
        }
    }
}
