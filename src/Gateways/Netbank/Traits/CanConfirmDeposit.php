<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Contracts\WalletResolver;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositResponseData;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Wallet\Events\DepositConfirmed;
use LBHurtado\Wallet\Jobs\BroadcastBalanceUpdated;

trait CanConfirmDeposit
{
    public function confirmDeposit(array $payload): bool
    {
        $response = DepositResponseData::from($payload);
        Log::info('Processing Netbank deposit confirmation', $response->toArray());

        $dto = RecipientAccountNumberData::fromRecipientAccountNumber(
            $response->recipientAccountNumber
        );

        try {
            $wallet = app(WalletResolver::class)->resolve($dto);
        } catch (\Throwable $e) {
            Log::error('Could not resolve recipient to a wallet', [
                'error' => $e->getMessage(),
                'payload' => $response->toArray(),
            ]);

            return false;
        }

        if (! $wallet instanceof Wallet) {
            Log::warning('No wallet found for reference or mobile', [
                'referenceCode' => $dto->referenceCode,
                'alias' => $dto->alias,
            ]);

            return false;
        }

        $this->transferToWallet($wallet, $response);

        // Hook for host app: sender processing, payment classification, etc.
        $this->afterDepositConfirmed($response, $wallet);

        return true;
    }

    protected function transferToWallet(Wallet $user, DepositResponseData $deposit): void
    {
        // NetBank sends amounts in pesos (e.g., 15 = ₱15.00)
        $amountInPesos = $deposit->amount;

        // TODO: Distinguish between top-up and settlement repayment
        // Current: All deposits treated as user top-up (works for both via manual confirmation)
        // Future: When QR encodes subject code, use CheckSubject to return Cash entity for direct repayment
        // For now, settlement payments are manually confirmed via /pay/voucher endpoint
        $logMessage = 'Treating deposit as user top-up';
        $transaction = TopupWalletAction::run($user, $amountInPesos)->deposit;
        Log::info($logMessage, ['amount_pesos' => $amountInPesos, 'amount_centavos' => $deposit->amount]);

        // Store full deposit payload plus simplified sender info for UI
        $transaction->meta = array_merge($deposit->toArray(), [
            'sender_name' => $deposit->sender->name,
            'sender_identifier' => $deposit->sender->accountNumber,
            'sender_institution' => $deposit->sender->institutionCode,
        ]);
        $transaction->save();

        DepositConfirmed::dispatch($transaction);

        // Queue job to broadcast balance update
        $wallet = $user instanceof \LBHurtado\Cash\Models\Cash ? $user : $user->wallet;
        if ($wallet) {
            BroadcastBalanceUpdated::dispatch($wallet->getKey());
        }
    }

    /**
     * Hook for host app to handle post-deposit logic.
     * Override in host app gateway class for: sender contact creation,
     * payment classification, relationship recording, etc.
     */
    protected function afterDepositConfirmed(DepositResponseData $deposit, Wallet $wallet): void
    {
        // Override in host app implementation
    }
}
