<?php

namespace LBHurtado\PaymentGateway\Contracts;

use Bavix\Wallet\Interfaces\Wallet;
use Brick\Money\Money;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Enums\SettlementRail;

interface PaymentGatewayInterface
{
    public function generate(string $account, Money $amount): string;

    /**
     * Confirm a deposit transaction sent by the payment gateway (e.g., QR Ph).
     *
     * @param  array  $payload  The validated deposit webhook payload.
     * @return bool Whether the confirmation was successful.
     */
    public function confirmDeposit(array $payload): bool;

    /**
     * Initiates a disbursement to the given wallet/account.
     *
     * @param  Wallet  $user  The user initiating the disbursement.
     * @param  array  $validated  The validated disbursement payload.
     */
    public function disburse(Wallet $user, DisburseInputData|array $validated): DisburseResponseData|bool;

    /**
     * Confirm a disbursement operation via its operation ID.
     */
    public function confirmDisbursement(string $operationId): bool;

    /**
     * Check the status of a disbursement transaction.
     *
     * @param  string  $transactionId  Gateway transaction ID
     * @return array{status: string, raw: array} Normalized status + raw response
     */
    public function checkDisbursementStatus(string $transactionId): array;

    /**
     * Check account balance.
     *
     * @param  string  $accountNumber  Account number to check
     * @return array{balance: int, available_balance: int, currency: string, as_of: ?string, raw: array}
     */
    public function checkAccountBalance(string $accountNumber): array;

    /**
     * Get the transaction fee for a specific settlement rail.
     *
     * @param  SettlementRail  $rail  The settlement rail
     * @return int Fee amount in minor units (centavos)
     */
    public function getRailFee(SettlementRail $rail): int;
}
