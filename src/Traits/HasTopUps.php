<?php

namespace LBHurtado\PaymentGateway\Traits;

use Illuminate\Support\Str;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Contracts\TopUpInterface;
use LBHurtado\PaymentGateway\Data\Netbank\DirectCheckout\CollectionRequestData;
use LBHurtado\PaymentGateway\Data\TopUp\TopUpResultData;
use LBHurtado\PaymentGateway\Exceptions\TopUpException;

trait HasTopUps
{
    /**
     * Relationship to top-ups.
     * Must be implemented by the model using this trait.
     */
    abstract public function topUps();

    /**
     * Initiate a top-up via payment gateway.
     *
     * @param  float  $amount  Amount to top-up
     * @param  string  $gateway  Payment gateway (netbank, stripe, etc.)
     * @param  string|null  $institutionCode  For netbank: GCASH, MAYA, etc.
     *
     * @throws TopUpException
     */
    public function initiateTopUp(
        float $amount,
        string $gateway = 'netbank',
        ?string $institutionCode = null
    ): TopUpResultData {
        // Validate amount
        $min = config('payment-gateway.top_up.min_amount', 1);
        $max = config('payment-gateway.top_up.max_amount', 50000);

        if ($amount < $min || $amount > $max) {
            throw TopUpException::invalidAmount($amount, $min, $max);
        }

        // Generate unique reference
        $referenceNo = $this->generateTopUpReference();

        // Get gateway instance
        $gatewayInstance = $this->getPaymentGateway($gateway);

        // Create collection request (for netbank)
        if ($gateway === 'netbank') {
            $request = CollectionRequestData::from([
                'reference_no' => $referenceNo,
                'amount' => (int) ($amount * 100), // Convert to cents
                'currency' => 'PHP',
                'institution_code' => $institutionCode,
            ]);

            $response = $gatewayInstance->initiateCollection($request);

            if (! $response) {
                throw TopUpException::initiationFailed('Gateway returned null response');
            }

            // Create top-up record
            $topUp = $this->topUps()->create([
                'gateway' => $gateway,
                'reference_no' => $referenceNo,
                'amount' => $amount,
                'currency' => 'PHP',
                'payment_status' => 'PENDING',
                'institution_code' => $institutionCode,
                'redirect_url' => $response->redirect_url,
            ]);

            return TopUpResultData::from([
                'reference_no' => $referenceNo,
                'redirect_url' => $response->redirect_url,
                'gateway' => $gateway,
                'amount' => $amount,
                'currency' => 'PHP',
                'institution_code' => $institutionCode,
            ]);
        }

        throw TopUpException::gatewayNotSupported($gateway);
    }

    /**
     * Get all top-ups for this entity.
     */
    public function getTopUps()
    {
        return $this->topUps()->latest()->get();
    }

    /**
     * Get pending top-ups.
     */
    public function getPendingTopUps()
    {
        return $this->topUps()->where('payment_status', 'PENDING')->latest()->get();
    }

    /**
     * Get paid top-ups.
     */
    public function getPaidTopUps()
    {
        return $this->topUps()->where('payment_status', 'PAID')->latest()->get();
    }

    /**
     * Get top-up by reference number.
     *
     * @throws TopUpException
     */
    public function getTopUpByReference(string $referenceNo): TopUpInterface
    {
        $topUp = $this->topUps()->where('reference_no', $referenceNo)->first();

        if (! $topUp) {
            throw TopUpException::referenceNotFound($referenceNo);
        }

        return $topUp;
    }

    /**
     * Get total amount topped up.
     */
    public function getTotalTopUps(): float
    {
        return $this->topUps()
            ->where('payment_status', 'PAID')
            ->sum('amount');
    }

    /**
     * Credit wallet from a paid top-up.
     * Expects model to have wallet functionality (Bavix Wallet).
     *
     * @param  mixed|null  $initiatedBy  User who initiated the top-up (for audit trail)
     * @param  string  $paymentMethod  Payment method used (e.g., 'Bank', 'Manual')
     */
    public function creditWalletFromTopUp(TopUpInterface $topUp, $initiatedBy = null, string $paymentMethod = 'Manual'): void
    {
        if (! $topUp->isPaid()) {
            return;
        }

        if (method_exists($this, 'deposit')) {
            // Bavix Wallet stores amounts in cents, so multiply by 100
            $amountInCents = (int) ($topUp->getAmount() * 100);

            $metadata = [
                'type' => 'top_up', // Legacy compatibility
                'deposit_type' => 'manual_topup',
                'payment_method' => $paymentMethod,
                'reference_no' => $topUp->getReferenceNo(),
                'gateway' => $topUp->getGateway(),
            ];

            // Add admin user info for audit trail
            if ($initiatedBy) {
                $metadata['sender_id'] = $initiatedBy->id ?? null;
                $metadata['sender_name'] = $initiatedBy->name ?? 'Unknown';
                $metadata['sender_identifier'] = $initiatedBy->email ?? 'unknown@system';
            }

            $this->deposit($amountInCents, $metadata);
        }
    }

    /**
     * Generate unique top-up reference number.
     */
    protected function generateTopUpReference(): string
    {
        return 'TOPUP-'.strtoupper(Str::random(10));
    }

    /**
     * Get payment gateway instance.
     */
    protected function getPaymentGateway(string $gateway): PaymentGatewayInterface
    {
        $gatewayClass = config('payment-gateway.gateway');

        return app($gatewayClass);
    }
}
