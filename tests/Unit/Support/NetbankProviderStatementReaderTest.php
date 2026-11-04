<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderStatementReader;
use LBHurtado\EmiCore\Data\Providers\ProviderStatementRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Support\NetbankProviderStatementReader;
use LBHurtado\PaymentGateway\Support\NetbankSettlementProvider;

it('registers and advertises a provider-neutral statement capability', function (): void {
    $readers = iterator_to_array(app()->tagged('emi.provider-statement-readers'));

    expect($readers)->toHaveCount(1)
        ->and($readers[0])->toBeInstanceOf(ProviderStatementReader::class)
        ->and(app(NetbankSettlementProvider::class)->manifest()->supports(ProviderCapability::StatementRead))->toBeTrue();
});

it('normalizes NetBank statement records without copying private fields', function (): void {
    config()->set('payment-gateway.netbank.funding.corporate_account_number', 'private-account');
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('accountTransactions')->once()->andReturn((function (): Generator {
        yield ['transaction_id' => 'txn-1', 'type' => 'Credit', 'amount' => ['cur' => 'PHP', 'num' => '14300'],
            'status' => 'Settled', 'date' => '2026-09-20T10:00:00Z', 'description' => 'private narrative'];
    })());

    $rows = iterator_to_array((new NetbankProviderStatementReader($client))->readStatement(statementRequest()));
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->direction)->toBe('credit')
        ->and($rows[0]->amountMinor)->toBe('14300')
        ->and(json_encode($rows[0]->toArray()))->not->toContain('private narrative')->not->toContain('private-account');
});

it('rejects malformed provider statement data', function (): void {
    config()->set('payment-gateway.netbank.funding.corporate_account_number', 'private-account');
    $client = Mockery::mock(NetbankFundingApiClient::class);
    $client->shouldReceive('accountTransactions')->once()->andReturn((function (): Generator {
        yield ['transaction_id' => 'txn-1', 'type' => 'Sideways', 'amount' => ['cur' => 'PHP', 'num' => '14300'],
            'status' => 'Settled', 'date' => '2026-09-20T10:00:00Z'];
    })());

    expect(fn () => iterator_to_array((new NetbankProviderStatementReader($client))->readStatement(statementRequest())))
        ->toThrow(InvalidArgumentException::class);
});

function statementRequest(): ProviderStatementRequestData
{
    return new ProviderStatementRequestData(
        'netbank', 'primary', 'resource', 'PHP',
        new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-09-21'), 5,
    );
}
