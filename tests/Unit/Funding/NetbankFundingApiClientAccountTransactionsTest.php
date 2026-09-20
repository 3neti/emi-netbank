<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;

beforeEach(function () {
    Cache::clear();
    Http::preventStrayRequests();
    config()->set('payment-gateway.netbank.funding', [
        'api_url' => 'https://api.netbank.test',
        'token_url' => 'https://auth.netbank.test/oauth2/token',
        'client_id' => 'sensitive-client-id',
        'client_secret' => 'sensitive-client-secret',
        'corporate_account_number' => '113-001-00001-9',
        'account_transactions_endpoint' => '/v1/accounts/{account_number}/transactions',
        'connect_timeout_seconds' => 5,
        'timeout_seconds' => 15,
        'account_history' => [
            'page_limit' => 2,
            'maximum_pages' => 3,
            'maximum_rows' => 5,
            'maximum_range_days' => 31,
        ],
    ]);
});

it('streams bounded corporate account history using OAuth and offset pagination', function () {
    $accountRequests = [];

    Http::fake(function (Request $request) use (&$accountRequests) {
        if ($request->url() === 'https://auth.netbank.test/oauth2/token') {
            return Http::response([
                'access_token' => 'sensitive-access-token',
                'expires_in' => 3600,
            ]);
        }

        $accountRequests[] = $request;
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return match ((int) ($query['offset'] ?? -1)) {
            0 => Http::response(['result' => [
                netbankAccountTransaction('transaction-1', 'Credit'),
                netbankAccountTransaction('transaction-2', 'Debit'),
            ]]),
            2 => Http::response(['result' => [
                netbankAccountTransaction('transaction-3', 'Debit'),
            ]]),
            default => Http::response(['result' => []]),
        };
    });

    $transactions = iterator_to_array(app(NetbankFundingApiClient::class)->accountTransactions(
        '113-001-00001-9',
        new DateTimeImmutable('2026-09-01T00:00:00+08:00'),
        new DateTimeImmutable('2026-09-21T00:00:00+08:00'),
    ));

    expect(array_column($transactions, 'transaction_id'))->toBe([
        'transaction-1',
        'transaction-2',
        'transaction-3',
    ])->and($accountRequests)->toHaveCount(2);

    foreach ($accountRequests as $index => $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        expect($request->url())->toStartWith(
            'https://api.netbank.test/v1/accounts/113-001-00001-9/transactions?',
        )->and($request->method())->toBe('GET')
            ->and($query)->toMatchArray([
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-21',
                'limit' => '2',
                'offset' => (string) ($index * 2),
            ]);
    }

    Http::assertSentCount(3);
});

it('honors the caller row ceiling without requesting an extra page', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'sensitive-access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/accounts/*' => Http::response([
            'transactions' => [
                netbankAccountTransaction('transaction-1', 'Credit'),
                netbankAccountTransaction('transaction-2', 'Debit'),
            ],
        ]),
    ]);

    $transactions = iterator_to_array(app(NetbankFundingApiClient::class)->accountTransactions(
        '113-001-00001-9',
        new DateTimeImmutable('2026-09-01'),
        new DateTimeImmutable('2026-09-21'),
        2,
    ));

    expect($transactions)->toHaveCount(2);
    Http::assertSentCount(2);
});

it('fails closed for invalid ranges limits payloads and repeating pages', function () {
    $client = app(NetbankFundingApiClient::class);

    expect(fn () => iterator_to_array($client->accountTransactions(
        '113-001-00001-9',
        new DateTimeImmutable('2026-09-21'),
        new DateTimeImmutable('2026-09-01'),
    )))->toThrow(InvalidArgumentException::class, 'end date must be after')
        ->and(fn () => iterator_to_array($client->accountTransactions(
            '113-001-00001-9',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-21'),
            6,
        )))->toThrow(InvalidArgumentException::class, 'row limit is invalid');

    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'sensitive-access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/accounts/*' => Http::response([
            'transactions' => [
                netbankAccountTransaction('transaction-1', 'Credit'),
                netbankAccountTransaction('transaction-2', 'Debit'),
            ],
        ]),
    ]);

    try {
        iterator_to_array($client->accountTransactions(
            '113-001-00001-9',
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-21'),
        ));
        $this->fail('Expected repeated provider pages to be rejected.');
    } catch (NetbankFundingRequestFailed $exception) {
        expect($exception->operation)->toBe('retrieve-account-transactions')
            ->and($exception->invalidResponse)->toBeTrue()
            ->and($exception->getMessage())->not->toContain('113-001-00001-9')
            ->not->toContain('sensitive-access-token');
    }
});

/** @return array<string, mixed> */
function netbankAccountTransaction(string $transactionId, string $type): array
{
    return [
        'transaction_id' => $transactionId,
        'type' => $type,
        'amount' => ['cur' => 'PHP', 'num' => '2500'],
        'status' => 'Settled',
        'date' => '2026-09-20T10:00:00.000Z',
    ];
}
