<?php

namespace LBHurtado\PaymentGateway\Adapters;

use Illuminate\Support\Str;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;

/**
 * Adapter that bridges emi-core's PayoutProvider contract
 * to the existing PaymentGatewayInterface implementation.
 */
class NetbankPayoutProvider implements PayoutProvider
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
    ) {}

    public function disburse(PayoutRequestData $request): PayoutResultData
    {
        $input = DisburseInputData::from([
            'reference' => $request->reference,
            'amount' => $request->amount,
            'account_number' => $request->account_number,
            'bank' => $request->bank_code,
            'via' => $request->settlement_rail,
            'voucher_id' => $request->subject_id ? (int) $request->subject_id : null,
            'voucher_code' => $request->subject_code,
            'user_id' => $request->user_id,
            'mobile' => $request->mobile,
        ]);

        $response = $this->gateway->disburse($this->resolveWallet(), $input);

        if ($response === false) {
            return new PayoutResultData(
                transaction_id: $request->reference,
                uuid: Str::uuid()->toString(),
                status: PayoutStatus::FAILED,
            );
        }

        return new PayoutResultData(
            transaction_id: $response->transaction_id,
            uuid: $response->uuid,
            status: $this->mapStatus($response->status),
        );
    }

    public function checkStatus(string $transactionId): PayoutResultData
    {
        $result = $this->gateway->checkDisbursementStatus($transactionId);

        return new PayoutResultData(
            transaction_id: $transactionId,
            uuid: Str::uuid()->toString(),
            status: $this->mapStatus($result['status']),
            metadata: $result['raw'] ?? null,
        );
    }

    /**
     * Map provider-specific status string to normalized PayoutStatus.
     */
    private function mapStatus(string $status): PayoutStatus
    {
        return match (strtoupper(str_replace(' ', '', $status))) {
            'PENDING' => PayoutStatus::PENDING,
            'FORSETTLEMENT' => PayoutStatus::PROCESSING,
            'SETTLED' => PayoutStatus::COMPLETED,
            'REJECTED' => PayoutStatus::FAILED,
            default => PayoutStatus::fromGeneric($status),
        };
    }

    /**
     * Resolve the wallet proxy for the gateway call.
     * Override this method or bind a custom resolver to change wallet resolution.
     */
    protected function resolveWallet(): \Bavix\Wallet\Interfaces\Wallet
    {
        return app(\LBHurtado\Wallet\Services\SystemUserResolverService::class)->resolve();
    }

    public function getRailFee(SettlementRail $rail): int
    {
        // Convert emi-core SettlementRail to payment-gateway SettlementRail
        $pgRail = \LBHurtado\PaymentGateway\Enums\SettlementRail::from($rail->value);

        return $this->gateway->getRailFee($pgRail);
    }
}
