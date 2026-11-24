<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\EmiCore\Enums\AccountTransferStatus;
use LBHurtado\PaymentGateway\Adapters\NetbankAccountTransferProvider;

beforeEach(function (): void {
    Cache::clear();
    Http::preventStrayRequests();
    config()->set('payment-gateway.netbank.funding', ['api_url' => 'https://netbank.test', 'token_url' => 'https://auth.test/token', 'client_id' => 'id', 'client_secret' => 'secret']);
});

it('submits integer minor units and the stable idempotency key over DIRECT', function (): void {
    Http::fake(['https://auth.test/token' => Http::response(['access_token' => 'token']), 'https://netbank.test/v1/transactions' => Http::response(['status' => 'Pending', 'transaction_id' => '123'])]);
    $request = new AccountTransferRequestData('netbank', 'reserve-1', 'stable-key', '111-111-11111-1', 'customer-1', '222-222-22222-2', 1000, 'PHP', 'Source', 'Reserve', 'NETBANK');
    $result = app(NetbankAccountTransferProvider::class)->submit($request);
    expect($result->status)->toBe(AccountTransferStatus::Pending)->and($result->providerTransactionId)->toBe('123');
    Http::assertSent(fn ($r) => $r->url() === 'https://netbank.test/v1/transactions' && $r->hasHeader('idempotency-key', 'stable-key') && $r['settlement_rail'] === 'DIRECT' && $r['amount']['num'] === '1000' && $r['source_account_number'] === '111-111-11111-1');
});

it('leaves timeouts and mismatched status identities uncertain', function (): void {
    Http::fake(['https://auth.test/token' => Http::response(['access_token' => 'token']), 'https://netbank.test/v1/transactions' => Http::failedConnection(), 'https://netbank.test/v1/transactions/123' => Http::response(['status' => 'Settled', 'transaction_id' => '456'])]);
    $request = new AccountTransferRequestData('netbank', 'reserve-1', 'stable-key', '111-111-11111-1', 'customer-1', '222-222-22222-2', 1000, 'PHP', 'Source', 'Reserve', 'NETBANK');
    $provider = app(NetbankAccountTransferProvider::class);
    expect($provider->submit($request)->status)->toBe(AccountTransferStatus::Uncertain)
        ->and($provider->checkStatus('123')->status)->toBe(AccountTransferStatus::Uncertain);
});

it('returns the complete independently retrieved bank evidence', function (): void {
    Http::fake(['https://auth.test/token' => Http::response(['access_token' => 'token']), 'https://netbank.test/v1/transactions/123' => Http::response([
        'transaction_id' => '123', 'status' => 'Settled', 'reference_id' => 'reserve-1',
        'source_account' => ['account_number' => '111-111-11111-1'], 'customer_id' => 'customer-1',
        'destination_account' => ['account_number' => '222-222-22222-2'],
        'amount' => ['cur' => 'PHP', 'num' => '1000'], 'fees' => [], 'settlement_rail' => 'DIRECT',
    ])]);
    $result = app(NetbankAccountTransferProvider::class)->checkStatus('123');
    expect($result->status)->toBe(AccountTransferStatus::Settled)->and($result->evidence->amountMinor)->toBe(1000)
        ->and($result->evidence->feeMinor)->toBe(0)->and($result->evidence->reference)->toBe('reserve-1');
});

it('does not report settlement when the provider omits the fee evidence', function (): void {
    Http::fake(['https://auth.test/token' => Http::response(['access_token' => 'token']), 'https://netbank.test/v1/transactions/123' => Http::response([
        'transaction_id' => '123', 'status' => 'Settled', 'reference_id' => 'reserve-1',
        'source_account' => ['account_number' => '111-111-11111-1'], 'customer_id' => 'customer-1',
        'destination_account' => ['account_number' => '222-222-22222-2'],
        'amount' => ['cur' => 'PHP', 'num' => '1000'], 'settlement_rail' => 'DIRECT',
    ])]);
    expect(app(NetbankAccountTransferProvider::class)->checkStatus('123')->status)->toBe(AccountTransferStatus::Uncertain);
});

it('rejects invalid idempotency key lengths before contacting NetBank', function (int $length): void {
    Http::fake(['https://auth.test/token' => Http::response(['access_token' => 'token'])]);
    $request = new AccountTransferRequestData('netbank', 'reserve-1', str_repeat('a', $length), '111-111-11111-1', 'customer-1', '222-222-22222-2', 1000, 'PHP', 'Source', 'Reserve', 'NETBANK');

    expect(fn () => app(NetbankAccountTransferProvider::class)->submit($request))
        ->toThrow(InvalidArgumentException::class, 'between 8 and 36 characters');
    Http::assertNothingSent();
})->with([7, 37]);

it('accepts both NetBank idempotency key length boundaries', function (int $length): void {
    Http::fake(['https://auth.test/token' => Http::response(['access_token' => 'token']), 'https://netbank.test/v1/transactions' => Http::response(['status' => 'Pending', 'transaction_id' => '123'])]);
    $key = str_repeat('a', $length);
    $request = new AccountTransferRequestData('netbank', 'reserve-1', $key, '111-111-11111-1', 'customer-1', '222-222-22222-2', 1000, 'PHP', 'Source', 'Reserve', 'NETBANK');

    expect(app(NetbankAccountTransferProvider::class)->submit($request)->status)->toBe(AccountTransferStatus::Pending);
    Http::assertSent(fn ($r) => $r->url() === 'https://netbank.test/v1/transactions' && $r->hasHeader('idempotency-key', $key));
})->with([8, 36]);

it('registers its account transfer adapter in the provider registry', function (): void {
    expect(config('emi.account_transfer_providers.netbank'))->toBe(NetbankAccountTransferProvider::class);
});
