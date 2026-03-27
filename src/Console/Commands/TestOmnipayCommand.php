<?php

namespace LBHurtado\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Omnipay\Support\OmnipayFactory;
use Omnipay\Common\GatewayInterface;

/**
 * Base command for testing Omnipay gateway operations
 *
 * Provides shared functionality for all test commands including:
 * - Gateway initialization
 * - Error handling
 * - Output formatting
 * - Safety checks
 */
abstract class TestOmnipayCommand extends Command
{
    protected GatewayInterface $gateway;

    protected string $gatewayName;

    /**
     * Initialize the gateway from options
     */
    protected function initializeGateway(): bool
    {
        $this->gatewayName = $this->option('gateway') ?? config('omnipay.default', 'netbank');

        try {
            $this->gateway = OmnipayFactory::create($this->gatewayName);

            // Check if in test mode
            if ($this->gateway->getTestMode()) {
                $this->warn('⚠️  Running in TEST MODE');
            } else {
                $this->warn('⚠️  Running in PRODUCTION MODE - Real transactions will be processed!');
            }

            $this->info("Gateway: {$this->gatewayName}");
            $this->newLine();

            return true;

        } catch (\Exception $e) {
            $this->error("Failed to initialize gateway '{$this->gatewayName}'");
            $this->error($e->getMessage());

            return false;
        }
    }

    /**
     * Handle command errors consistently
     */
    protected function handleError(\Throwable $e, string $operation): void
    {
        $this->error("✗ {$operation} failed!");
        $this->error($e->getMessage());

        if ($this->output->isVerbose()) {
            $this->line($e->getTraceAsString());
        }

        Log::channel('single')->error("[Omnipay Test] {$operation} failed", [
            'gateway' => $this->gatewayName ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Log operation for auditing
     */
    protected function logOperation(string $operation, array $data = []): void
    {
        Log::channel('single')->info("[Omnipay Test] {$operation}", array_merge([
            'gateway' => $this->gatewayName,
            'timestamp' => now()->toDateTimeString(),
        ], $data));
    }

    /**
     * Format money amount
     */
    protected function formatMoney(int $amountInCentavos, string $currency = 'PHP'): string
    {
        $amount = $amountInCentavos / 100;

        return '₱'.number_format($amount, 2).' '.$currency;
    }

    /**
     * Display success message
     */
    protected function success(string $message): void
    {
        $this->info("✓ {$message}");
    }

    /**
     * Confirm dangerous operation
     */
    protected function confirmDangerousOperation(string $operation, array $details): bool
    {
        $this->warn("⚠️  This will initiate a REAL {$operation}!");
        $this->newLine();

        foreach ($details as $label => $value) {
            $this->line("  <fg=yellow>{$label}:</>  {$value}");
        }

        $this->newLine();

        return $this->confirm('Do you want to continue?', false);
    }

    /**
     * Display result table
     */
    protected function displayResults(array $data): void
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [$key, $value];
        }

        $this->table(['Field', 'Value'], $rows);
    }
}
