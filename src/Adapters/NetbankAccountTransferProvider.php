<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Adapters;

use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\IdempotentAccountTransferProvider;
use LBHurtado\EmiCore\Data\Providers\AccountTransferEvidenceData;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\EmiCore\Data\Providers\AccountTransferResultData;
use LBHurtado\EmiCore\Enums\AccountTransferStatus;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use Throwable;

final readonly class NetbankAccountTransferProvider implements IdempotentAccountTransferProvider
{
    public const int MIN_IDEMPOTENCY_KEY_LENGTH = 8;

    public const int MAX_IDEMPOTENCY_KEY_LENGTH = 36;

    public function __construct(private NetbankFundingApiClient $client) {}

    public function providerCode(): string
    {
        return 'netbank';
    }

    public function submit(AccountTransferRequestData $request): AccountTransferResultData
    {
        if ($request->provider !== 'netbank' || $request->currency !== 'PHP') {
            throw new InvalidArgumentException('NetBank requires a PHP account transfer.');
        }
        $keyLength = strlen($request->idempotencyKey);
        if ($keyLength < self::MIN_IDEMPOTENCY_KEY_LENGTH || $keyLength > self::MAX_IDEMPOTENCY_KEY_LENGTH) {
            throw new InvalidArgumentException('NetBank idempotency keys must be between 8 and 36 characters.');
        }

        try {
            $response = $this->client->submitAccountTransfer([
                'reference_id' => $request->reference,
                'settlement_rail' => 'DIRECT',
                'source_account_number' => $request->sourceAccountNumber,
                'sender' => ['name' => $request->sourceName, 'customer_id' => $request->sourceCustomerId],
                'destination_account' => ['bank_code' => $request->destinationBankCode, 'account_number' => $request->destinationAccountNumber],
                'recipient' => ['name' => $request->destinationName],
                'amount' => ['cur' => $request->currency, 'num' => (string) $request->amountMinor],
            ], $request->idempotencyKey);
        } catch (Throwable) {
            return new AccountTransferResultData(AccountTransferStatus::Uncertain);
        }

        return $this->result($response);
    }

    public function checkStatus(string $providerTransactionId): AccountTransferResultData
    {
        if (trim($providerTransactionId) === '') {
            throw new InvalidArgumentException('The provider transaction reference is required.');
        }
        try {
            $response = $this->client->accountTransferStatus($providerTransactionId);
        } catch (Throwable) {
            return new AccountTransferResultData(AccountTransferStatus::Uncertain, $providerTransactionId);
        }

        return $this->result($response, $providerTransactionId, requireEvidence: true);
    }

    /** @param array<string, mixed> $response */
    private function result(array $response, ?string $expectedId = null, bool $requireEvidence = false): AccountTransferResultData
    {
        $id = $response['transaction_id'] ?? $expectedId;
        if (! is_string($id) || trim($id) === '' || ($expectedId !== null && ! hash_equals($expectedId, $id))) {
            return new AccountTransferResultData(AccountTransferStatus::Uncertain, $expectedId);
        }
        $status = match (strtolower((string) ($response['status'] ?? ''))) {
            'settled' => AccountTransferStatus::Settled,
            'pending', 'forsettlement' => AccountTransferStatus::Pending,
            'rejected' => AccountTransferStatus::Rejected,
            default => AccountTransferStatus::Uncertain,
        };

        $evidence = $requireEvidence && in_array($status, [AccountTransferStatus::Settled, AccountTransferStatus::Rejected], true)
            ? $this->evidence($response) : null;
        if ($requireEvidence && $status === AccountTransferStatus::Settled && $evidence === null) {
            return new AccountTransferResultData(AccountTransferStatus::Uncertain, $id);
        }

        return new AccountTransferResultData($status, $id, $evidence);
    }

    /** @param array<string, mixed> $response */
    private function evidence(array $response): ?AccountTransferEvidenceData
    {
        $fields = [data_get($response, 'reference_id'), data_get($response, 'source_account.account_number'),
            data_get($response, 'destination_account.account_number'), data_get($response, 'customer_id'),
            data_get($response, 'amount.cur'), data_get($response, 'settlement_rail')];
        foreach ($fields as $field) {
            if (! is_string($field) || trim($field) === '') {
                return null;
            }
        }
        $amount = data_get($response, 'amount.num');
        $fees = $response['fees'] ?? null;
        if (! is_string($amount) || ! ctype_digit($amount) || filter_var($amount, FILTER_VALIDATE_INT) === false || ! is_array($fees)) {
            return null;
        }
        $feeMinor = 0;
        foreach ($fees as $fee) {
            $value = data_get($fee, 'amount.num');
            if (! is_string($value) || ! ctype_digit($value) || filter_var($value, FILTER_VALIDATE_INT) === false
                || data_get($fee, 'amount.cur') !== $fields[4] || (int) $value > PHP_INT_MAX - $feeMinor) {
                return null;
            }
            $feeMinor += (int) $value;
        }

        return new AccountTransferEvidenceData($fields[0], $fields[1], $fields[2], $fields[3], (int) $amount, $fields[4], $fields[5], $feeMinor);
    }
}
