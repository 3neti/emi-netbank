<?php

namespace LBHurtado\PaymentGateway\Console\Commands;

/**
 * Test GCash Memo Visibility (Smoke Test)
 *
 * Systematically tests whether GCash displays memo/remarks sent via NetBank InstaPay.
 * Runs 5 test cases with varying message lengths and formats.
 *
 * ⚠️ WARNING: This command processes REAL transactions!
 *
 * Usage:
 *   php artisan omnipay:test-gcash-memo 09171234567
 *   php artisan omnipay:test-gcash-memo 09171234567 --amount=10
 */
class TestGCashMemoCommand extends TestOmnipayCommand
{
    protected $signature = 'omnipay:test-gcash-memo
                            {gcash_mobile : GCash mobile number (09XXXXXXXXX)}
                            {--amount=10 : Amount in pesos per test (default: 10)}
                            {--gateway=netbank : The gateway to use}';

    protected $description = 'Test GCash memo visibility via NetBank InstaPay (⚠️ REAL TRANSACTIONS)';

    /**
     * Test cases to run
     */
    protected array $testCases = [
        [
            'name' => 'Short brand-first (≤20 chars)',
            'remarks' => 'XCHG THANK YOU',
            'sender_info' => null,
        ],
        [
            'name' => 'Exact boundary (35 chars)',
            'remarks' => 'XCHG WATER ACCT 123456789012345',
            'sender_info' => null,
        ],
        [
            'name' => 'Over boundary (>35 chars)',
            'remarks' => 'XCHG WATER ACCT 1234567890 PAID TODAY',
            'sender_info' => null,
        ],
        [
            'name' => 'Special characters',
            'remarks' => 'XCHG INV#A-1029 PAID',
            'sender_info' => null,
        ],
        [
            'name' => 'With sender info',
            'remarks' => 'XCHG THANK YOU',
            'sender_info' => 'x-Change Redemption',
        ],
    ];

    protected array $results = [];

    public function handle(): int
    {
        $this->warn('⚠️  GCASH MEMO SMOKE TEST - REAL MONEY WILL BE TRANSFERRED');
        $this->line(str_repeat('=', 50));
        $this->newLine();

        // Initialize gateway
        if (! $this->initializeGateway()) {
            return self::FAILURE;
        }

        try {
            // Parse inputs
            $gcashMobile = $this->argument('gcash_mobile');
            $amountPerTest = (float) $this->option('amount');
            $totalAmount = $amountPerTest * count($this->testCases);

            // Validate mobile number format
            if (! preg_match('/^09\d{9}$/', $gcashMobile)) {
                $this->error('Invalid GCash mobile format. Expected: 09XXXXXXXXX');

                return self::FAILURE;
            }

            // Convert to centavos
            $amountInCentavos = (int) ($amountPerTest * 100);

            // Display test plan
            $this->info('Test Plan:');
            $this->displayResults([
                'Recipient' => $gcashMobile,
                'Bank' => 'GCash (GXCHPHM2XXX)',
                'Settlement Rail' => 'INSTAPAY',
                'Amount per Test' => $this->formatMoney($amountInCentavos),
                'Number of Tests' => count($this->testCases),
                'Total Amount' => $this->formatMoney((int) ($totalAmount * 100)),
            ]);
            $this->newLine();

            $this->warn('Test Cases:');
            foreach ($this->testCases as $index => $testCase) {
                $this->line(sprintf(
                    '  %d. %s - "%s"%s',
                    $index + 1,
                    $testCase['name'],
                    $testCase['remarks'],
                    $testCase['sender_info'] ? ' + sender_info' : ''
                ));
            }
            $this->newLine();

            // Global confirmation
            if (! $this->confirm('Run all '.count($this->testCases)." tests for total {$this->formatMoney((int) ($totalAmount * 100))}?", false)) {
                $this->warn('Test cancelled.');

                return self::SUCCESS;
            }

            $this->newLine();
            $this->info('Starting tests...');
            $this->newLine();

            // Run each test case
            foreach ($this->testCases as $index => $testCase) {
                $testNumber = $index + 1;

                $this->line(str_repeat('-', 50));
                $this->info("Test {$testNumber}/".count($this->testCases).": {$testCase['name']}");
                $this->line(str_repeat('-', 50));

                if (! $this->runTestCase($testNumber, $testCase, $gcashMobile, $amountInCentavos)) {
                    $this->error("Test {$testNumber} failed. Stopping test suite.");
                    break;
                }

                // Wait between tests
                if ($testNumber < count($this->testCases)) {
                    $this->line('Waiting 10 seconds before next test...');
                    sleep(10);
                    $this->newLine();
                }
            }

            // Generate CSV report
            $this->newLine();
            $this->info('Generating CSV report...');
            $csvPath = $this->generateCsvReport();
            $this->success("CSV report saved: {$csvPath}");
            $this->newLine();

            // Display summary
            $this->displaySummary();

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->handleError($e, 'GCash Memo Test');

            return self::FAILURE;
        }
    }

    protected function runTestCase(int $testNumber, array $testCase, string $gcashMobile, int $amountInCentavos): bool
    {
        try {
            $reference = 'GCASH-MEMO-TEST-'.$testNumber.'-'.strtoupper(uniqid());

            // Display test details
            $details = [
                'Remarks' => $testCase['remarks'].' ('.strlen($testCase['remarks']).' chars)',
            ];

            if ($testCase['sender_info']) {
                $details['Sender Info'] = $testCase['sender_info'];
            }

            $details['Reference'] = $reference;

            $this->displayResults($details);
            $this->newLine();

            // Per-test confirmation
            if (! $this->confirm("Proceed with test {$testNumber}?", true)) {
                $this->warn("Test {$testNumber} skipped.");
                $this->results[] = [
                    'test_case' => $testNumber,
                    'name' => $testCase['name'],
                    'timestamp' => now()->toDateTimeString(),
                    'reference_id' => $reference,
                    'amount' => $amountInCentavos / 100,
                    'remarks_sent' => $testCase['remarks'],
                    'sender_info_sent' => $testCase['sender_info'] ?? '',
                    'netbank_tx_id' => 'SKIPPED',
                    'status' => 'skipped',
                    'notes' => '',
                ];

                return true;
            }

            // Prepare disburse params
            $disburseParams = [
                'amount' => $amountInCentavos,
                'accountNumber' => $gcashMobile,
                'bankCode' => 'GXCHPHM2XXX',
                'reference' => $reference,
                'via' => 'INSTAPAY',
                'currency' => 'PHP',
                'remarks' => $testCase['remarks'],
            ];

            if ($testCase['sender_info']) {
                $disburseParams['additionalSenderInfo'] = $testCase['sender_info'];
            }

            // Log operation
            $this->logOperation("GCash Memo Test {$testNumber}", [
                'test_case' => $testCase['name'],
                'remarks' => $testCase['remarks'],
                'sender_info' => $testCase['sender_info'],
                'reference' => $reference,
            ]);

            // Execute disbursement
            $this->info('Processing disbursement...');
            $response = $this->gateway->disburse($disburseParams)->send();

            // Handle response
            if ($response->isSuccessful()) {
                $txId = $response->getOperationId() ?? 'N/A';

                $this->success("Test {$testNumber} completed!");
                $this->displayResults([
                    'Transaction ID' => $txId,
                    'Status' => $response->getStatus() ?? 'pending',
                ]);

                $this->newLine();
                $this->line('<fg=yellow>📱 CHECK YOUR GCASH APP NOW!</>');
                $this->line("<fg=yellow>   Transaction: {$reference}</>");
                $this->line('<fg=yellow>   Look for memo/remarks in list and details view</>');
                $this->newLine();

                // Store result
                $this->results[] = [
                    'test_case' => $testNumber,
                    'name' => $testCase['name'],
                    'timestamp' => now()->toDateTimeString(),
                    'reference_id' => $reference,
                    'amount' => $amountInCentavos / 100,
                    'remarks_sent' => $testCase['remarks'],
                    'sender_info_sent' => $testCase['sender_info'] ?? '',
                    'netbank_tx_id' => $txId,
                    'status' => 'success',
                    'notes' => '[MANUAL: Check GCash app and fill this field]',
                ];

                $this->logOperation("Test {$testNumber} Success", [
                    'transaction_id' => $txId,
                    'reference' => $reference,
                ]);

                return true;

            } else {
                $this->error("✗ Test {$testNumber} failed!");
                $this->error($response->getMessage());

                // Store failed result
                $this->results[] = [
                    'test_case' => $testNumber,
                    'name' => $testCase['name'],
                    'timestamp' => now()->toDateTimeString(),
                    'reference_id' => $reference,
                    'amount' => $amountInCentavos / 100,
                    'remarks_sent' => $testCase['remarks'],
                    'sender_info_sent' => $testCase['sender_info'] ?? '',
                    'netbank_tx_id' => 'FAILED',
                    'status' => 'failed',
                    'notes' => 'Error: '.$response->getMessage(),
                ];

                $this->logOperation("Test {$testNumber} Failed", [
                    'error' => $response->getMessage(),
                    'reference' => $reference,
                ]);

                return false;
            }

        } catch (\Throwable $e) {
            $this->error("✗ Test {$testNumber} threw exception!");
            $this->error($e->getMessage());

            // Store exception result
            $this->results[] = [
                'test_case' => $testNumber,
                'name' => $testCase['name'],
                'timestamp' => now()->toDateTimeString(),
                'reference_id' => $reference ?? 'N/A',
                'amount' => $amountInCentavos / 100,
                'remarks_sent' => $testCase['remarks'],
                'sender_info_sent' => $testCase['sender_info'] ?? '',
                'netbank_tx_id' => 'EXCEPTION',
                'status' => 'exception',
                'notes' => 'Exception: '.$e->getMessage(),
            ];

            return false;
        }
    }

    protected function generateCsvReport(): string
    {
        $timestamp = now()->format('Y-m-d_His');
        $filename = "gcash-memo-test-{$timestamp}.csv";
        $path = storage_path($filename);

        $fp = fopen($path, 'w');

        // Write header
        fputcsv($fp, [
            'Test Case',
            'Name',
            'Timestamp',
            'Reference ID',
            'Amount (PHP)',
            'Remarks Sent',
            'Sender Info Sent',
            'NetBank Tx ID',
            'Status',
            'Notes (MANUAL: Fill this after checking GCash)',
        ]);

        // Write data
        foreach ($this->results as $result) {
            fputcsv($fp, [
                $result['test_case'],
                $result['name'],
                $result['timestamp'],
                $result['reference_id'],
                $result['amount'],
                $result['remarks_sent'],
                $result['sender_info_sent'],
                $result['netbank_tx_id'],
                $result['status'],
                $result['notes'],
            ]);
        }

        fclose($fp);

        return $path;
    }

    protected function displaySummary(): void
    {
        $this->line(str_repeat('=', 50));
        $this->info('TEST SUMMARY');
        $this->line(str_repeat('=', 50));

        $successful = count(array_filter($this->results, fn ($r) => $r['status'] === 'success'));
        $failed = count(array_filter($this->results, fn ($r) => $r['status'] === 'failed'));
        $skipped = count(array_filter($this->results, fn ($r) => $r['status'] === 'skipped'));

        $this->displayResults([
            'Total Tests' => count($this->results),
            'Successful' => $successful,
            'Failed' => $failed,
            'Skipped' => $skipped,
        ]);

        $this->newLine();
        $this->warn('NEXT STEPS:');
        $this->line('1. Open your GCash app and check each transaction');
        $this->line('2. For each transaction, note if memo/remarks is visible');
        $this->line('3. Open the CSV report and fill the "Notes" column');
        $this->line('4. Document: "Memo shown: [exact text]" or "No memo visible"');
        $this->line('5. Screenshot both list view and details view');
        $this->newLine();
    }
}
