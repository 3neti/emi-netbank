<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\PaymentGateway\Contracts\TopUpInterface;

class TopUp extends Model implements TopUpInterface
{
    protected $fillable = [
        'user_id',
        'gateway',
        'reference_no',
        'amount',
        'currency',
        'payment_status',
        'payment_id',
        'institution_code',
        'redirect_url',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // TopUpInterface implementation
    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function getReferenceNo(): string
    {
        return $this->reference_no;
    }

    public function getAmount(): float
    {
        return (float) $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->payment_status;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirect_url;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'PAID';
    }

    public function isPending(): bool
    {
        return $this->payment_status === 'PENDING';
    }

    public function isFailed(): bool
    {
        return in_array($this->payment_status, ['FAILED', 'EXPIRED']);
    }

    public function markAsPaid(string $paymentId): void
    {
        $this->update([
            'payment_status' => 'PAID',
            'payment_id' => $paymentId,
            'paid_at' => now(),
        ]);
    }

    public function getOwner()
    {
        return $this->user;
    }
}
