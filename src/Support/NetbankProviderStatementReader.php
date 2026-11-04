<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\ProviderStatementReader;
use LBHurtado\EmiCore\Data\Providers\ProviderStatementRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderStatementTransactionData;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use Throwable;

final readonly class NetbankProviderStatementReader implements ProviderStatementReader
{
    public function __construct(private NetbankFundingApiClient $client) {}

    public function providerCode(): string
    {
        return 'netbank';
    }

    public function readStatement(ProviderStatementRequestData $request): iterable
    {
        if (strtolower(trim($request->provider)) !== $this->providerCode()) {
            throw new InvalidArgumentException('NetBank statement provider mismatch.');
        }

        $accountNumber = trim((string) config('payment-gateway.netbank.funding.corporate_account_number'));
        if ($accountNumber === '') {
            throw new InvalidArgumentException('The configured NetBank corporate account is unavailable.');
        }

        foreach ($this->client->accountTransactions($accountNumber, $request->from, $request->until, $request->maximumRows) as $transaction) {
            $id = trim((string) ($transaction['transaction_id'] ?? ''));
            $direction = mb_strtolower(trim((string) ($transaction['type'] ?? '')));
            $currency = mb_strtoupper(trim((string) data_get($transaction, 'amount.cur', '')));
            $amount = trim((string) data_get($transaction, 'amount.num', ''));
            $status = mb_strtolower(trim((string) ($transaction['status'] ?? '')));
            $timestamp = trim((string) ($transaction['date'] ?? ''));

            if ($id === '' || mb_strlen($id) > 191 || preg_match('/[\x00-\x1F\x7F]/u', $id) === 1
                || ! in_array($direction, ['credit', 'debit'], true)
                || $currency !== strtoupper(trim($request->currency)) || preg_match('/^\d{1,24}$/', $amount) !== 1
                || $status === '' || mb_strlen($status) > 64 || preg_match('/[\x00-\x1F\x7F]/u', $status) === 1
                || $timestamp === '') {
                throw new InvalidArgumentException('The provider returned invalid transaction data.');
            }

            try {
                $occurredAt = new DateTimeImmutable($timestamp);
            } catch (Throwable) {
                throw new InvalidArgumentException('The provider returned an invalid transaction timestamp.');
            }

            yield new ProviderStatementTransactionData($id, $direction, $amount, $currency, $status, $occurredAt);
        }
    }
}
