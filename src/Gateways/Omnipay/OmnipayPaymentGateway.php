<?php

namespace LBHurtado\PaymentGateway\Gateways\Omnipay;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transaction;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\CanConfirmDeposit;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use LBHurtado\PaymentGateway\Omnipay\Support\OmnipayFactory;
use LBHurtado\Wallet\Events\DisbursementConfirmed;
use Omnipay\Common\GatewayInterface;

/**
 * Omnipay-based payment gateway adapter.
 *
 * Implements PaymentGatewayInterface using the Omnipay framework,
 * providing settlement rail validation, EMI detection, and KYC workarounds.
 */
class OmnipayPaymentGateway implements PaymentGatewayInterface
{
    use CanConfirmDeposit;

    private const DEBUG = false;

    protected GatewayInterface $gateway;

    protected BankRegistry $bankRegistry;

    public function __construct()
    {
        $gatewayName = config('payment-gateway.default', 'netbank');
        $this->gateway = OmnipayFactory::create($gatewayName);
        $this->bankRegistry = app(BankRegistry::class);
    }

    /**
     * Disburse funds to a recipient via Omnipay.
     *
     * IMPORTANT: This method handles ONLY the external disbursement API call.
     * Wallet operations (deposits/withdrawals) are handled by the calling pipeline
     * (DisburseCash) to maintain separation of concerns:
     *
     * Flow:
     * 1. DisburseCash pipeline calls WithdrawCash::run() - withdraws from cash wallet
     * 2. DisburseCash pipeline calls this method - sends funds via external gateway
     * 3. This method stores operation metadata and returns response
     *
     * Historical note: Previously, this method performed TopupWalletAction before
     * the API call, which caused duplicate deposits (one from PersistCash during
     * generation, one from this method during redemption). This was removed to fix
     * the bug where settlement vouchers showed ₱100 instead of ₱50.
     *
     * @param  Wallet  $wallet  The wallet being disbursed (for metadata only)
     * @param  DisburseInputData|array  $validated  Disbursement data
     * @return DisburseResponseData|bool Response data or false on failure
     */
    public function disburse(
        Wallet $wallet,
        DisburseInputData|array $validated
    ): DisburseResponseData|bool {
        // Convert to array if DisburseInputData
        $data = $validated instanceof DisburseInputData
            ? $validated->toArray()
            : $validated;

        if (self::DEBUG) {
            Log::debug('[OmnipayPaymentGateway] Starting disbursement', [
                'wallet_id' => $wallet->getKey(),
                'reference' => $data['reference'],
                'amount' => $data['amount'],
                'bank' => $data['bank'],
                'via' => $data['via'],
            ]);
        }

        $amount = $data['amount'];
        $currency = config('disbursement.currency', 'PHP');

        // Log disbursement attempt (for audit trail)
        $attempt = DisbursementAttempt::create([
            'voucher_id' => $data['voucher_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'voucher_code' => $data['voucher_code'] ?? null,
            'amount' => $amount,
            'currency' => $currency,
            'mobile' => $data['mobile'] ?? null,
            'bank_code' => $data['bank'],
            'account_number' => $data['account_number'],
            'settlement_rail' => $data['via'],
            'gateway' => config('payment-gateway.default', 'netbank'),
            'reference_id' => $data['reference'],
            'status' => 'pending',
            'request_payload' => $data,
            'attempted_at' => now(),
        ]);

        // Validate settlement rail
        try {
            $rail = SettlementRail::from($data['via']);
            $this->validateBankSupportsRail($data['bank'], $rail);
        } catch (\Throwable $e) {
            Log::error('[OmnipayPaymentGateway] Settlement rail validation failed', [
                'error' => $e->getMessage(),
                'bank' => $data['bank'],
                'via' => $data['via'],
            ]);

            return false;
        }

        DB::beginTransaction();

        try {
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
                $errorType = $response->getData()['error_type'] ?? 'unknown';
                $logLevel = $errorType === 'network_timeout' ? 'warning' : 'warning';

                // Update attempt record with failure
                $attempt->update([
                    'status' => 'failed',
                    'error_type' => $errorType,
                    'error_message' => $response->getMessage(),
                    'response_payload' => $response->getData(),
                    'completed_at' => now(),
                ]);

                Log::$logLevel('[OmnipayPaymentGateway] Disbursement failed', [
                    'message' => $response->getMessage(),
                    'code' => $response->getCode(),
                    'error_type' => $errorType,
                    'rail' => $rail->value,
                    'bank' => $data['bank'],
                    'reference' => $data['reference'],
                    'is_timeout' => $errorType === 'network_timeout',
                ]);
                DB::rollBack();

                return false;
            }

            // Update attempt record with success
            $attempt->update([
                'status' => 'success',
                'gateway_transaction_id' => $response->getOperationId(),
                'response_payload' => $response->getData(),
                'completed_at' => now(),
            ]);

            DB::commit();

            Log::info('[OmnipayPaymentGateway] Disbursement initiated', [
                'operation_id' => $response->getOperationId(),
                'rail' => $rail->value,
                'bank' => $data['bank'],
                'amount' => $amount,
                'status' => $response->getStatus(),
            ]);

            // Return response DTO
            // Note: uuid is generated here as this method no longer creates wallet transactions.
            // Wallet operations are handled by the calling pipeline (DisburseCash + WithdrawCash).
            return DisburseResponseData::from([
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'transaction_id' => $response->getOperationId() ?? 'PENDING',
                'status' => $response->getStatus() ?? 'Pending',
            ]);

        } catch (\Throwable $e) {
            // Update attempt record with error
            if (isset($attempt)) {
                $attempt->update([
                    'status' => 'failed',
                    'error_type' => class_basename($e),
                    'error_message' => $e->getMessage(),
                    'error_details' => [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ],
                    'completed_at' => now(),
                ]);
            }

            Log::error('[OmnipayPaymentGateway] Disbursement error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'rail' => $rail->value ?? null,
                'bank' => $data['bank'] ?? null,
            ]);
            DB::rollBack();

            return false;
        }
    }

    /**
     * Generate QR code for payment collection.
     *
     * @param  string  $account  Account number
     * @param  Money  $amount  Amount to collect
     * @param  array  $merchantData  Optional merchant information (merchant, user objects)
     * @return string QR code data (base64 PNG)
     */
    public function generate(string $account, Money $amount, array $merchantData = []): string
    {
        if (self::DEBUG) {
            Log::debug('[OmnipayPaymentGateway] Generating QR code', [
                'account' => $account,
                'amount' => $amount->getAmount()->toFloat(),
            ]);
        }

        // Convert to minor units
        $amountInCentavos = $amount->getMinorAmount()->toInt();

        $params = [
            'accountNumber' => $account,
            'amount' => $amountInCentavos,
            'currency' => $amount->getCurrency()->getCurrencyCode(),
        ];

        // Render merchant name using template service if merchant and user provided
        if (isset($merchantData['merchant']) && isset($merchantData['user'])) {
            $merchant = $merchantData['merchant'];
            $templateService = app(\App\Services\MerchantNameTemplateService::class);
            // Use merchant's template (fallback to config or default)
            $template = $merchant->merchant_name_template
                ?? config('payment-gateway.qr_merchant_name.template', '{name} - {city}');

            $merchantName = $templateService->render(
                $template,
                $merchant,
                $merchantData['user']
            );

            $params['merchantName'] = $merchantName;

            // NetBank API requires merchant_city as separate field (even though it may not display)
            // We send city to satisfy API requirements
            $params['merchantCity'] = $merchant->city ?? 'Manila';

            if (self::DEBUG) {
                Log::debug('[OmnipayPaymentGateway] Rendered merchant name', [
                    'template' => $template,
                    'rendered' => $merchantName,
                    'city' => $params['merchantCity'],
                ]);
            }
        }

        $response = $this->gateway->generateQr($params)->send();

        if (! $response->isSuccessful()) {
            Log::error('[OmnipayPaymentGateway] QR generation failed', [
                'message' => $response->getMessage(),
            ]);
            throw new \RuntimeException('Failed to generate QR code: '.$response->getMessage());
        }

        Log::info('[OmnipayPaymentGateway] QR code generated successfully');

        return $response->getQrCode();
    }

    // confirmDeposit() method is implemented via CanConfirmDeposit trait

    /**
     * Confirm a disbursement operation.
     *
     * @param  string  $operationId  Gateway operation ID
     * @return bool Success status
     */
    public function confirmDisbursement(string $operationId): bool
    {
        try {
            $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)
                ->firstOrFail();

            if (self::DEBUG) {
                Log::debug('[OmnipayPaymentGateway] Found transaction for confirmation', [
                    'operation_id' => $operationId,
                    'transaction_uuid' => $transaction->uuid,
                ]);
            }

            // Confirm the transaction
            $transaction->payable->confirm($transaction);

            // Dispatch event
            DisbursementConfirmed::dispatch($transaction);

            $rail = $transaction->meta['settlement_rail'] ?? 'unknown';
            $bank = $transaction->meta['bank_code'] ?? 'unknown';

            Log::info('[OmnipayPaymentGateway] Disbursement confirmed', [
                'operation_id' => $operationId,
                'transaction_uuid' => $transaction->uuid,
                'rail' => $rail,
                'bank' => $bank,
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('[OmnipayPaymentGateway] Confirm disbursement failed', [
                'operation_id' => $operationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check the status of a disbursement transaction.
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

                Log::warning('[OmnipayPaymentGateway] Status check failed', [
                    'transaction_id' => $transactionId,
                    'message' => $errorMessage,
                ]);

                return ['status' => 'error', 'raw' => [], 'error' => 'Gateway returned error: '.$errorMessage];
            }

            $rawStatus = $response->getStatus();
            $normalized = \LBHurtado\PaymentGateway\Enums\DisbursementStatus::fromGateway('netbank', $rawStatus);

            Log::info('[OmnipayPaymentGateway] Status checked', [
                'transaction_id' => $transactionId,
                'raw_status' => $rawStatus,
                'normalized_status' => $normalized->value,
            ]);

            return [
                'status' => $normalized->value,
                'raw' => $response->getRawData(),
            ];
        } catch (\Throwable $e) {
            Log::error('[OmnipayPaymentGateway] Status check error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['status' => 'error', 'raw' => [], 'error' => 'Status check failed: '.$e->getMessage()];
        }
    }

    /**
     * Check account balance.
     *
     * @param  string  $accountNumber  Account number to check
     * @return array{balance: int, available_balance: int, currency: string, as_of: ?string, raw: array}
     */
    public function checkAccountBalance(string $accountNumber): array
    {
        try {
            if (self::DEBUG) {
                Log::debug('[OmnipayPaymentGateway] Checking balance', [
                    'account' => $accountNumber,
                ]);
            }

            $response = $this->gateway->checkBalance([
                'accountNumber' => $accountNumber,
            ])->send();

            if (! $response->isSuccessful()) {
                Log::warning('[OmnipayPaymentGateway] Balance check failed', [
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

            if (self::DEBUG) {
                Log::info('[OmnipayPaymentGateway] Balance checked', [
                    'account' => $accountNumber,
                    'balance' => $response->getBalance(),
                ]);
            }

            return [
                'balance' => $response->getBalance(),
                'available_balance' => $response->getAvailableBalance(),
                'currency' => $response->getCurrency(),
                'as_of' => $response->getAsOf(),
                'raw' => $response->getData(),
            ];

        } catch (\Throwable $e) {
            Log::error('[OmnipayPaymentGateway] Balance check error', [
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
     * Get the transaction fee for a specific settlement rail.
     *
     * @param  SettlementRail  $rail  The settlement rail
     * @return int Fee amount in minor units (centavos)
     */
    public function getRailFee(SettlementRail $rail): int
    {
        $railsConfig = config('omnipay.gateways.netbank.options.rails', []);
        $railConfig = $railsConfig[$rail->value] ?? [];

        return $railConfig['fee'] ?? 0;
    }

    /**
     * Validate that bank supports the selected settlement rail.
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
