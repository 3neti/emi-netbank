<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * NetBank Check Disbursement Status Response
 *
 * Response from NetBank transaction status check API.
 *
 * Possible status values from NetBank:
 * - Pending: Payout received, debited from source
 * - ForSettlement: Forwarded to ACH, sent to receiving institution
 * - Settled: Processed and credited to target account
 * - Rejected: Processed but rejected, returned to source
 *
 * Example response data:
 * <code>
 * {
 *   "transaction_id": "260741510",
 *   "status": "Settled",
 *   "amount": 5000,
 *   "currency": "PHP",
 *   "recipient_account": "09173011987",
 *   "bank_code": "GXCHPHM2XXX",
 *   "settlement_rail": "INSTAPAY",
 *   "created_at": "2025-11-14T06:00:00Z",
 *   "settled_at": "2025-11-14T06:05:00Z"
 * }
 * </code>
 */
class CheckDisbursementStatusResponse extends AbstractResponse
{
    /**
     * Check if the request was successful
     */
    public function isSuccessful(): bool
    {
        return isset($this->data['status']) && ! isset($this->data['error']);
    }

    /**
     * Get the transaction status
     * Returns NetBank-specific status: Pending, ForSettlement, Settled, Rejected
     */
    public function getStatus(): string
    {
        return $this->data['status'] ?? 'Pending';
    }

    /**
     * Get the transaction ID
     */
    public function getTransactionId(): ?string
    {
        return $this->data['transaction_id'] ?? null;
    }

    /**
     * Get the transaction amount in minor units (centavos)
     */
    public function getAmount(): ?int
    {
        return $this->data['amount'] ?? null;
    }

    /**
     * Get the currency code
     */
    public function getCurrency(): string
    {
        return $this->data['currency'] ?? 'PHP';
    }

    /**
     * Get the recipient account number
     */
    public function getRecipientAccount(): ?string
    {
        return $this->data['recipient_account'] ?? null;
    }

    /**
     * Get the bank code (BIC/SWIFT)
     */
    public function getBankCode(): ?string
    {
        return $this->data['bank_code'] ?? null;
    }

    /**
     * Get the settlement rail (INSTAPAY or PESONET)
     */
    public function getSettlementRail(): ?string
    {
        return $this->data['settlement_rail'] ?? null;
    }

    /**
     * Get the transaction creation timestamp
     */
    public function getCreatedAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    /**
     * Get the settlement timestamp (if settled)
     */
    public function getSettledAt(): ?string
    {
        return $this->data['settled_at'] ?? null;
    }

    /**
     * Get the rejection reason (if rejected)
     */
    public function getRejectionReason(): ?string
    {
        return $this->data['rejection_reason'] ?? null;
    }

    /**
     * Get the complete raw response data
     */
    public function getRawData(): array
    {
        return $this->data;
    }

    /**
     * Get the error message if request failed
     */
    public function getMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->data['message']
            ?? $this->data['error']
            ?? 'Unknown error occurred';
    }

    /**
     * Get the error code if request failed
     */
    public function getCode(): ?string
    {
        return $this->data['code'] ?? null;
    }

    /**
     * Check if transaction is in final state (no more updates expected)
     */
    public function isFinal(): bool
    {
        $status = strtoupper(str_replace(' ', '', $this->getStatus()));

        return in_array($status, ['SETTLED', 'REJECTED']);
    }

    /**
     * Check if transaction is still pending
     */
    public function isPending(): bool
    {
        $status = strtoupper(str_replace(' ', '', $this->getStatus()));

        return in_array($status, ['PENDING', 'FORSETTLEMENT']);
    }
}
