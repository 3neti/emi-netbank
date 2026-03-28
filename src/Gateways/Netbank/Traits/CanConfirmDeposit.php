<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Contracts\WalletResolver;
use LBHurtado\PaymentGateway\Data\ConfirmedDepositData;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositResponseData;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;

trait CanConfirmDeposit
{
    /**
     * Parse and validate a deposit callback, resolve the recipient wallet.
     * Returns normalized data — does NOT execute business side effects.
     *
     * @throws \RuntimeException If wallet cannot be resolved
     */
    public function parseDeposit(array $payload): ConfirmedDepositData
    {
        $response = DepositResponseData::from($payload);
        Log::info('Processing Netbank deposit confirmation', $response->toArray());

        $dto = RecipientAccountNumberData::fromRecipientAccountNumber(
            $response->recipientAccountNumber
        );

        $wallet = app(WalletResolver::class)->resolve($dto);

        return new ConfirmedDepositData(
            reference_code: $dto->referenceCode,
            amount: (float) $response->amount,
            currency: 'PHP',
            channel: $response->channel,
            sender_name: $response->sender->name,
            sender_account: $response->sender->accountNumber,
            sender_institution: $response->sender->institutionCode,
            transfer_type: $response->transferType,
            reference_number: $response->referenceNumber,
            registration_time: $response->registrationTime,
            wallet: $wallet,
            raw_payload: $response->toArray(),
        );
    }

    /**
     * Backward-compatible deposit confirmation.
     * Parses deposit, then delegates to applyDepositEffects() for business side effects.
     * Host app should override applyDepositEffects() to customize behavior.
     */
    public function confirmDeposit(array $payload): bool
    {
        try {
            $deposit = $this->parseDeposit($payload);
        } catch (\Throwable $e) {
            Log::error('Could not parse/resolve deposit', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return false;
        }

        return $this->applyDepositEffects($deposit);
    }

    /**
     * Apply business side effects for a confirmed deposit.
     * Override in host app gateway for custom behavior (wallet top-up, events, notifications).
     *
     * Default implementation: no-op, returns true.
     */
    protected function applyDepositEffects(ConfirmedDepositData $deposit): bool
    {
        return true;
    }
}
