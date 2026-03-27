<?php

namespace LBHurtado\PaymentGateway\Console\Commands;

/**
 * Generate QR code via Omnipay gateway
 *
 * Usage:
 *   php artisan omnipay:qr 1234567890 100
 *   php artisan omnipay:qr 1234567890 100 --gateway=netbank
 */
class GenerateQrCommand extends TestOmnipayCommand
{
    protected $signature = 'omnipay:qr
                            {account : Account number for QR code}
                            {amount? : Amount in pesos (optional - for fixed-amount QR)}
                            {--gateway=netbank : The gateway to use}
                            {--save= : File path to save QR code}';

    protected $description = 'Generate QR code for payment';

    public function handle(): int
    {
        $this->info('Generate QR Code');
        $this->line(str_repeat('=', 50));
        $this->newLine();

        // Initialize gateway
        if (! $this->initializeGateway()) {
            return self::FAILURE;
        }

        try {
            $account = $this->argument('account');
            $amount = $this->argument('amount');
            $reference = 'QR-'.strtoupper(uniqid());

            // Build request params
            $params = [
                'accountNumber' => $account,
                'reference' => $reference,
            ];

            // Add amount if specified (for fixed-amount QR)
            if ($amount) {
                $amountInCentavos = (int) ((float) $amount * 100);
                $params['amount'] = $amountInCentavos;
                $params['currency'] = 'PHP';

                $this->info("Generating FIXED-AMOUNT QR code for {$this->formatMoney($amountInCentavos)}...");
            } else {
                $this->info('Generating DYNAMIC-AMOUNT QR code...');
            }

            $this->info("Account: {$account}");
            $this->newLine();

            $this->logOperation('Generate QR', [
                'account' => $account,
                'amount' => $amount ?? 'dynamic',
                'reference' => $reference,
            ]);

            // Make request
            $response = $this->gateway->generateQr($params)->send();

            // Handle response
            if ($response->isSuccessful()) {
                $this->success('QR Code generated successfully!');
                $this->newLine();

                $qrCode = $response->getQrCode();
                $qrUrl = $response->getQrUrl();
                $qrId = $response->getQrId();
                $expiresAt = $response->getExpiresAt();

                // Display results
                $results = [
                    'QR ID' => $qrId ?? 'N/A',
                    'Account' => $account,
                    'Reference' => $reference,
                ];

                if ($amount) {
                    $results['Amount'] = $this->formatMoney($amountInCentavos);
                } else {
                    $results['Amount'] = 'Dynamic (user enters amount)';
                }

                if ($qrUrl) {
                    $results['QR URL'] = $qrUrl;
                }

                if ($expiresAt) {
                    $results['Expires At'] = $expiresAt;
                }

                $this->displayResults($results);

                // Display QR code data
                if ($qrCode) {
                    $this->newLine();
                    $this->line('<fg=cyan>QR Code Data:</>');
                    $this->line(substr($qrCode, 0, 100).'...');

                    // Save to file if requested
                    if ($savePath = $this->option('save')) {
                        file_put_contents($savePath, $qrCode);
                        $this->newLine();
                        $this->success("QR code saved to: {$savePath}");
                    }
                }

                $this->newLine();
                $this->line('<fg=yellow>Note: Use this QR code for testing payments. Share via QR URL or encode the data.</>');

                $this->logOperation('Generate QR Success', [
                    'qr_id' => $qrId,
                    'url' => $qrUrl,
                ]);

                return self::SUCCESS;

            } else {
                $this->error('✗ Failed to generate QR code');
                $this->error($response->getMessage());

                if ($response->getCode()) {
                    $this->line("Error Code: {$response->getCode()}");
                }

                $this->logOperation('Generate QR Failed', [
                    'error' => $response->getMessage(),
                    'code' => $response->getCode(),
                ]);

                return self::FAILURE;
            }

        } catch (\Throwable $e) {
            $this->handleError($e, 'Generate QR');

            return self::FAILURE;
        }
    }
}
