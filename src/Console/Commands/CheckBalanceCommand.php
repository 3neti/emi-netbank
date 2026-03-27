<?php

namespace LBHurtado\PaymentGateway\Console\Commands;

/**
 * Check account balance via Omnipay gateway
 *
 * Usage:
 *   php artisan omnipay:balance
 *   php artisan omnipay:balance --gateway=icash
 */
class CheckBalanceCommand extends TestOmnipayCommand
{
    protected $signature = 'omnipay:balance
                            {--gateway=netbank : The gateway to use (netbank, icash)}
                            {--account= : Account number to check (uses config default if not provided)}';

    protected $description = 'Check account balance from payment gateway';

    public function handle(): int
    {
        $this->info('Checking Account Balance');
        $this->line(str_repeat('=', 50));
        $this->newLine();

        // Initialize gateway
        if (! $this->initializeGateway()) {
            return self::FAILURE;
        }

        try {
            // Get account number
            $accountNumber = $this->option('account')
                ?? config('omnipay.test_account')
                ?? config('disbursement.account_number');

            if (! $accountNumber) {
                $this->error('No account number provided. Use --account option or set OMNIPAY_TEST_ACCOUNT in .env');

                return self::FAILURE;
            }

            $this->info("Checking balance for account: {$accountNumber}...");
            $this->logOperation('Check Balance', ['account' => $accountNumber]);

            // Make request
            $response = $this->gateway->checkBalance([
                'accountNumber' => $accountNumber,
            ])->send();

            // Handle response
            if ($response->isSuccessful()) {
                $this->newLine();
                $this->success('Balance retrieved successfully!');
                $this->newLine();

                $balance = $response->getBalance();
                $availableBalance = $response->getAvailableBalance();
                $currency = $response->getCurrency();
                $asOf = $response->getAsOf();

                // Display results
                $results = [
                    'Account' => $response->getAccountNumber() ?? $accountNumber,
                    'Balance' => $this->formatMoney($balance, $currency),
                    'Available Balance' => $this->formatMoney($availableBalance, $currency),
                    'Currency' => $currency,
                ];

                if ($asOf) {
                    $results['As Of'] = $asOf;
                }

                $this->displayResults($results);

                // Log success
                $this->logOperation('Check Balance Success', [
                    'balance' => $balance,
                    'currency' => $currency,
                ]);

                return self::SUCCESS;

            } else {
                $this->error('✗ Failed to retrieve balance');
                $this->error($response->getMessage());

                if ($response->getCode()) {
                    $this->line("Error Code: {$response->getCode()}");
                }

                $this->logOperation('Check Balance Failed', [
                    'error' => $response->getMessage(),
                    'code' => $response->getCode(),
                ]);

                return self::FAILURE;
            }

        } catch (\Throwable $e) {
            $this->handleError($e, 'Check Balance');

            return self::FAILURE;
        }
    }
}
