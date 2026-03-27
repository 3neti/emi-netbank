<?php

namespace LBHurtado\PaymentGateway\Contracts;

interface TopUpInterface
{
    /**
     * Get the payment gateway used for this top-up.
     */
    public function getGateway(): string;

    /**
     * Get the unique reference number for this top-up.
     */
    public function getReferenceNo(): string;

    /**
     * Get the top-up amount.
     */
    public function getAmount(): float;

    /**
     * Get the currency code.
     */
    public function getCurrency(): string;

    /**
     * Get the current payment status.
     */
    public function getStatus(): string;

    /**
     * Get the redirect URL for payment.
     */
    public function getRedirectUrl(): ?string;

    /**
     * Check if the top-up has been paid.
     */
    public function isPaid(): bool;

    /**
     * Check if the top-up is pending payment.
     */
    public function isPending(): bool;

    /**
     * Check if the top-up has failed or expired.
     */
    public function isFailed(): bool;

    /**
     * Mark the top-up as paid.
     */
    public function markAsPaid(string $paymentId): void;

    /**
     * Get the user/owner of this top-up.
     */
    public function getOwner();
}
