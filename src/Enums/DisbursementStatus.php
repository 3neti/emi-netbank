<?php

namespace LBHurtado\PaymentGateway\Enums;

/**
 * Generic disbursement status enum
 *
 * Normalizes gateway-specific statuses into a common set of states.
 *
 * NetBank statuses:
 * - Pending: Payout received, debited from source
 * - ForSettlement: Forwarded to ACH, sent to receiving institution
 * - Settled: Processed and credited to target account
 * - Rejected: Processed but rejected, returned to source
 */
enum DisbursementStatus: string
{
    case PENDING = 'pending';           // Initial state after disbursement
    case PROCESSING = 'processing';     // In transit (ForSettlement in NetBank)
    case COMPLETED = 'completed';       // Successfully delivered (Settled in NetBank)
    case FAILED = 'failed';             // Permanent failure (Rejected in NetBank)
    case CANCELLED = 'cancelled';       // User/admin cancelled
    case REFUNDED = 'refunded';         // Money returned

    /**
     * Map gateway-specific status to generic status
     *
     * @param  string  $gateway  Gateway name (netbank, icash, paypal, stripe, etc.)
     * @param  string  $status  Gateway-specific status string
     * @return self Normalized status
     */
    public static function fromGateway(string $gateway, string $status): self
    {
        return match (strtolower($gateway)) {
            'netbank' => self::fromNetbank($status),
            'icash' => self::fromICash($status),
            'paypal' => self::fromPayPal($status),
            'stripe' => self::fromStripe($status),
            'gcash' => self::fromGCash($status),
            default => self::fromGeneric($status),
        };
    }

    /**
     * Map NetBank-specific statuses
     *
     * NetBank API Documentation:
     * https://virtual.netbank.ph/docs#operation/Disburse-To-Account_RetrieveAccount-To-AccountTransactionDetails
     */
    private static function fromNetbank(string $status): self
    {
        return match (strtoupper(str_replace(' ', '', $status))) {
            'PENDING' => self::PENDING,
            'FORSETTLEMENT' => self::PROCESSING,  // Forwarded to ACH
            'SETTLED' => self::COMPLETED,         // Credited to target account
            'REJECTED' => self::FAILED,           // Rejected by receiving institution
            default => self::PENDING,
        };
    }

    /**
     * Map iCash-specific statuses
     */
    private static function fromICash(string $status): self
    {
        // TODO: Map iCash statuses when available
        return self::fromGeneric($status);
    }

    /**
     * Map PayPal-specific statuses
     */
    private static function fromPayPal(string $status): self
    {
        return match (strtoupper($status)) {
            'PENDING', 'CREATED' => self::PENDING,
            'SUCCESS', 'COMPLETED' => self::COMPLETED,
            'FAILED', 'DENIED' => self::FAILED,
            'CANCELLED' => self::CANCELLED,
            'REFUNDED' => self::REFUNDED,
            default => self::PENDING,
        };
    }

    /**
     * Map Stripe-specific statuses
     */
    private static function fromStripe(string $status): self
    {
        return match (strtolower($status)) {
            'pending' => self::PENDING,
            'in_transit' => self::PROCESSING,
            'paid' => self::COMPLETED,
            'failed' => self::FAILED,
            'canceled' => self::CANCELLED,
            default => self::PENDING,
        };
    }

    /**
     * Map GCash-specific statuses
     */
    private static function fromGCash(string $status): self
    {
        // GCash statuses similar to e-wallet patterns
        return match (strtoupper($status)) {
            'PENDING' => self::PENDING,
            'SUCCESS', 'COMPLETED' => self::COMPLETED,
            'FAILED', 'ERROR' => self::FAILED,
            default => self::PENDING,
        };
    }

    /**
     * Map generic status strings
     */
    private static function fromGeneric(string $status): self
    {
        return match (strtolower($status)) {
            'pending' => self::PENDING,
            'processing', 'in_transit', 'forsettlement' => self::PROCESSING,
            'completed', 'success', 'settled' => self::COMPLETED,
            'failed', 'error', 'rejected' => self::FAILED,
            'cancelled', 'canceled' => self::CANCELLED,
            'refunded' => self::REFUNDED,
            default => self::PENDING,
        };
    }

    /**
     * Check if status is final (no more updates expected)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
        ]);
    }

    /**
     * Check if status is pending or in progress
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::PROCESSING,
        ]);
    }

    /**
     * Get badge variant for UI display
     */
    public function getBadgeVariant(): string
    {
        return match ($this) {
            self::PENDING => 'secondary',
            self::PROCESSING => 'default',
            self::COMPLETED => 'success',
            self::FAILED => 'destructive',
            self::CANCELLED => 'outline',
            self::REFUNDED => 'default',
        };
    }

    /**
     * Get display label for UI
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }
}
