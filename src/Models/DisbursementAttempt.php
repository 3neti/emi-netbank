<?php

namespace LBHurtado\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;

class DisbursementAttempt extends Model
{
    protected $fillable = [
        'voucher_id',
        'user_id',
        'voucher_code',
        'amount',
        'currency',
        'mobile',
        'bank_code',
        'account_number',
        'settlement_rail',
        'gateway',
        'reference_id',
        'gateway_transaction_id',
        'status',
        'error_type',
        'error_message',
        'error_details',
        'request_payload',
        'response_payload',
        'attempted_at',
        'completed_at',
        'attempt_count',
        'last_checked_at',
    ];

    protected $casts = [
        'error_details' => 'array',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    // ── Relationships ──

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes: Status ──

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Attempts in an unknown outcome state (bank may or may not have processed).
     * These are attempts that failed locally but may have been received by the bank.
     */
    public function scopeUnknown(Builder $query): Builder
    {
        return $query->where('status', 'failed')
            ->where(function (Builder $q) {
                $q->whereNotNull('gateway_transaction_id')
                    ->orWhere('error_type', 'network_timeout')
                    ->orWhere('error_type', 'ConnectionException');
            });
    }

    /**
     * Attempts that need reconciliation: pending, or failed with possible bank-side processing.
     */
    public function scopeReconcilable(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', 'pending')
                ->orWhere(function (Builder $inner) {
                    $inner->where('status', 'failed')
                        ->where(function (Builder $timeout) {
                            $timeout->whereNotNull('gateway_transaction_id')
                                ->orWhere('error_type', 'network_timeout')
                                ->orWhere('error_type', 'ConnectionException');
                        });
                });
        });
    }

    // ── Scopes: Lookup ──

    public function scopeByVoucherCode(Builder $query, string $code): Builder
    {
        return $query->where('voucher_code', $code);
    }

    public function scopeByReference(Builder $query, string $reference): Builder
    {
        return $query->where('reference_id', $reference);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('attempted_at', '>=', now()->subDays($days));
    }

    public function scopeByGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeByErrorType(Builder $query, string $errorType): Builder
    {
        return $query->where('error_type', $errorType);
    }

    // ── State Mutation Helpers ──

    /**
     * Mark this attempt as successfully confirmed by the bank.
     */
    public function markAsSuccess(?string $gatewayTransactionId = null): self
    {
        $data = [
            'status' => 'success',
            'completed_at' => now(),
        ];

        if ($gatewayTransactionId) {
            $data['gateway_transaction_id'] = $gatewayTransactionId;
        }

        $this->update($data);

        return $this;
    }

    /**
     * Mark this attempt as confirmed failed (bank rejected).
     */
    public function markAsFailed(string $errorMessage, ?string $errorType = null): self
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'error_type' => $errorType ?? $this->error_type,
            'completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark this attempt as cancelled (operator abandoned).
     */
    public function markAsCancelled(string $reason = 'Cancelled by operator'): self
    {
        $this->update([
            'status' => 'cancelled',
            'error_message' => $reason,
            'completed_at' => now(),
        ]);

        return $this;
    }

    // ── Query Helpers ──

    /**
     * Check if this attempt has a known bank-side transaction ID.
     */
    public function hasGatewayTransactionId(): bool
    {
        return ! empty($this->gateway_transaction_id);
    }

    /**
     * Check if this attempt is in a final state.
     */
    public function isFinal(): bool
    {
        return in_array($this->status, ['success', 'failed', 'cancelled']);
    }

    /**
     * Check if this attempt needs reconciliation.
     */
    public function needsReconciliation(): bool
    {
        if ($this->status === 'pending') {
            return true;
        }

        // Failed but bank may have processed (has transaction ID or was a timeout)
        if ($this->status === 'failed') {
            return $this->hasGatewayTransactionId()
                || in_array($this->error_type, ['network_timeout', 'ConnectionException']);
        }

        return false;
    }
}
