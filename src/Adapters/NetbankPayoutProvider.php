<?php

namespace LBHurtado\PaymentGateway\Adapters;

use Illuminate\Support\Str;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;

/**
 * Adapter that bridges emi-core's PayoutProvider contract
 * to the existing PaymentGatewayInterface implementation.
 */
class NetbankPayoutProvider implements PayoutProvider
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected WalletProxy $walletProxy,
    ) {}

    public function disburse(PayoutRequestData $request): PayoutResultData
    {
        $input = DisburseInputData::from([
            'reference' => $request->reference,
            'amount' => $request->amount,
            'account_number' => $request->account_number,
            'bank' => $request->bank_code,
            'via' => $request->settlement_rail,
            'reference_id' => $request->external_id ? (int) $request->external_id : null,
            'reference_code' => $request->external_code,
            'user_id' => $request->user_id,
            'mobile' => $request->mobile,
        ]);

        $response = $this->gateway->disburse($this->walletProxy->resolve(), $input);

        if ($response === false) {
            return new PayoutResultData(
                transaction_id: $request->reference,
                uuid: Str::uuid()->toString(),
                status: PayoutStatus::FAILED,
                provider: 'netbank',
                metadata: [
                    'provider_submission_accepted' => false,
                    'failure_phase' => 'provider_submission',
                    'failure_code' => 'submission_not_accepted',
                    'failure_message' => 'NetBank did not accept the payout submission.',
                ],
            );
        }

        return new PayoutResultData(
            transaction_id: $response->transaction_id,
            uuid: $response->uuid,
            status: $this->mapStatus($response->status),
            provider: 'netbank',
        );
    }

    public function checkStatus(string $transactionId): PayoutResultData
    {
        $result = $this->gateway->checkDisbursementStatus($transactionId);
        $metadata = $this->enrichStatusMetadata($result['raw'] ?? null);

        return new PayoutResultData(
            transaction_id: $transactionId,
            uuid: Str::uuid()->toString(),
            status: $this->mapStatus($result['status']),
            provider: 'netbank',
            metadata: $metadata,
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
     * @return array<string, mixed>|null
     */
    private function enrichStatusMetadata(mixed $metadata): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        $rejectionReason = $this->extractRejectionReason($metadata);

        if ($rejectionReason !== null) {
            $metadata['rejection_reason'] = $rejectionReason;
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function extractRejectionReason(array $metadata): ?string
    {
        foreach (array_reverse((array) ($metadata['status_details'] ?? [])) as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $message = $detail['message'] ?? null;

            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
        }

        foreach (['rejection_reason', 'message', 'error'] as $key) {
            $message = $metadata[$key] ?? null;

            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
        }

        return null;
    }

    public function getRailFee(SettlementRail $rail): int
    {
        $railsConfig = config('omnipay.gateways.netbank.options.rails', []);

        return $railsConfig[$rail->value]['fee'] ?? 0;
    }
}
