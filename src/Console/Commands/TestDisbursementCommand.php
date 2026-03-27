<?php

namespace LBHurtado\PaymentGateway\Console\Commands;

use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\PaymentGateway\Enums\SettlementRail;

/**
 * Test disbursement via Omnipay gateway
 *
 * ⚠️ WARNING: This command processes REAL transactions!
 *
 * Usage:
 *   php artisan omnipay:disburse 100 09171234567 GXCHPHM2XXX INSTAPAY
 *   php artisan omnipay:disburse 100 09171234567 GXCHPHM2XXX INSTAPAY --reference=TEST-001
 */
class TestDisbursementCommand extends TestOmnipayCommand
{
    protected $signature = 'omnipay:disburse
                            {amount : Amount in pesos (e.g., 100 for ₱100.00)}
                            {account : Account number or mobile number}
                            {bank : Bank SWIFT/BIC code (e.g., GXCHPHM2XXX for GCash)}
                            {rail : Settlement rail (INSTAPAY or PESONET)}
                            {--reference= : Custom reference (auto-generated if not provided)}
                            {--gateway=netbank : The gateway to use}
                            {--remarks= : Optional remarks/memo for recipient}
                            {--sender-info= : Optional additional sender information}
                            {--no-confirm : Skip confirmation prompt (dangerous!)}';

    protected $description = 'Test disbursement to a recipient (⚠️  REAL TRANSACTION)';

    public function handle(): int
    {
        $this->warn('⚠️  DISBURSEMENT TEST - REAL MONEY WILL BE TRANSFERRED');
        $this->line(str_repeat('=', 50));
        $this->newLine();

        // Initialize gateway
        if (! $this->initializeGateway()) {
            return self::FAILURE;
        }

        try {
            // Parse inputs
            $amount = (float) $this->argument('amount');
            $account = $this->argument('account');
            $bankCode = strtoupper($this->argument('bank'));
            $railName = strtoupper($this->argument('rail'));
            $reference = $this->option('reference') ?? 'TEST-'.strtoupper(uniqid());

            // Validate rail
            try {
                $rail = SettlementRail::from($railName);
            } catch (\ValueError $e) {
                $this->error("Invalid settlement rail: {$railName}. Use INSTAPAY or PESONET.");

                return self::FAILURE;
            }

            // Convert to centavos
            $amountInCentavos = (int) ($amount * 100);

            // Get bank info
            $bankRegistry = app(BankRegistry::class);
            $bankInfo = $bankRegistry->find($bankCode);

            if (! $bankInfo) {
                $this->error("Bank code '{$bankCode}' not found in registry.");
                $this->line("Use 'omnipay:list-banks' to see available banks.");

                return self::FAILURE;
            }

            $bankName = $bankInfo['full_name'];
            $isEMI = $bankRegistry->isEMI($bankCode);

            // Check if bank supports rail
            if (! $bankRegistry->supportsRail($bankCode, $rail)) {
                $this->error("{$bankName} does not support {$railName}.");
                $supportedRails = array_keys($bankRegistry->supportedSettlementRails($bankCode));
                if ($supportedRails) {
                    $this->line('Supported rails: '.implode(', ', $supportedRails));
                }

                return self::FAILURE;
            }

            // Get rail config for fee
            $railConfig = $this->gateway->getRailConfig($rail);
            $fee = $railConfig['fee'] ?? 0;

            // Display details
            $details = [
                'Amount' => $this->formatMoney($amountInCentavos),
                'Account' => $account,
                'Bank' => "{$bankName} ({$bankCode})".($isEMI ? ' [EMI]' : ''),
                'Settlement Rail' => $railName,
                'Fee' => $this->formatMoney($fee),
                'Total Debit' => $this->formatMoney($amountInCentavos + $fee),
                'Reference' => $reference,
            ];

            // Add remarks if provided
            if ($remarks = $this->option('remarks')) {
                $details['Remarks'] = $remarks;
            }

            // Add sender info if provided
            if ($senderInfo = $this->option('sender-info')) {
                $details['Sender Info'] = $senderInfo;
            }

            $this->info('Disbursement Details:');
            $this->displayResults($details);
            $this->newLine();

            // Confirmation
            if (! $this->option('no-confirm')) {
                if (! $this->confirmDangerousOperation('DISBURSEMENT', $details)) {
                    $this->warn('Operation cancelled.');

                    return self::SUCCESS;
                }
            }

            // Process disbursement
            $this->info('Processing disbursement...');
            $this->logOperation('Disburse', [
                'amount' => $amountInCentavos,
                'account' => $account,
                'bank' => $bankCode,
                'rail' => $railName,
                'reference' => $reference,
            ]);

            $disburseParams = [
                'amount' => $amountInCentavos,
                'accountNumber' => $account,
                'bankCode' => $bankCode,
                'reference' => $reference,
                'via' => $railName,
                'currency' => 'PHP',
            ];

            // Add remarks if provided
            if ($remarks = $this->option('remarks')) {
                $disburseParams['remarks'] = $remarks;
            }

            // Add sender info if provided
            if ($senderInfo = $this->option('sender-info')) {
                $disburseParams['additionalSenderInfo'] = $senderInfo;
            }

            $response = $this->gateway->disburse($disburseParams)->send();

            // Handle response
            if ($response->isSuccessful()) {
                $this->newLine();
                $this->success('Disbursement initiated successfully!');
                $this->newLine();

                $results = [
                    'Transaction ID' => $response->getOperationId() ?? $response->getTransactionUuid() ?? 'N/A',
                    'Status' => $response->getStatus() ?? 'pending',
                    'Settlement Rail' => $railName,
                    'Reference' => $reference,
                ];

                $this->displayResults($results);

                $this->newLine();
                $this->line('<fg=yellow>Note: Transaction may take time to process. Check your dashboard for status updates.</>');

                $this->logOperation('Disburse Success', [
                    'transaction_id' => $response->getOperationId(),
                    'status' => $response->getStatus(),
                ]);

                return self::SUCCESS;

            } else {
                $this->error('✗ Disbursement failed!');
                $this->error($response->getMessage());

                if ($response->getCode()) {
                    $this->line("Error Code: {$response->getCode()}");
                }

                $this->logOperation('Disburse Failed', [
                    'error' => $response->getMessage(),
                    'code' => $response->getCode(),
                ]);

                return self::FAILURE;
            }

        } catch (\Throwable $e) {
            $this->handleError($e, 'Disbursement');

            return self::FAILURE;
        }
    }
}
