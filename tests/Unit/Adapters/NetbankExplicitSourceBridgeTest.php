<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transaction;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\DisburseResponse;
use LBHurtado\PaymentGateway\Services\OmnipayBridge;
use Omnipay\Common\GatewayInterface;

it('passes the selected source and customer id to the NetBank gateway without storing them in wallet metadata', function () {
    $registry = Mockery::mock(BankRegistry::class);
    $registry->shouldReceive('supportsRail')->once()->andReturnTrue();
    $registry->shouldReceive('isEMI')->once()->andReturnTrue();
    app()->instance(BankRegistry::class, $registry);

    $response = Mockery::mock(DisburseResponse::class);
    $response->shouldReceive('isSuccessful')->once()->andReturnTrue();
    $response->shouldReceive('getOperationId')->andReturn('operation-1');
    $response->shouldReceive('getStatus')->andReturn('Pending');
    $request = Mockery::mock();
    $request->shouldReceive('send')->once()->andReturn($response);
    $gateway = Mockery::mock(GatewayInterface::class);
    $gateway->shouldReceive('disburse')->once()->with(Mockery::on(fn (array $data): bool => $data['sourceAccountNumber'] === '991100001234'
        && $data['senderCustomerId'] === '1234'
        && $data['amount'] === 1000
    ))->andReturn($request);
    $transaction = Mockery::mock(Transaction::class)->makePartial();
    $transaction->uuid = 'test-uuid';
    $transaction->shouldReceive('save')->once()->andReturnTrue();
    $wallet = Mockery::mock(Wallet::class);
    $wallet->shouldReceive('withdraw')->once()->andReturn($transaction);
    $wallet->shouldReceive('getKey')->once()->andReturn(1);

    (new OmnipayBridge($gateway))->disburse($wallet, [
        'reference' => 'test-ref', 'amount' => 10, 'account_number' => '09170000000',
        'bank' => 'GXCHPHM2XXX', 'via' => 'INSTAPAY',
        'source_account' => ['provider' => 'netbank', 'account_number' => '991100001234', 'customer_id' => '1234'],
    ]);

    expect($transaction->meta['payload'])->not->toHaveKey('source_account');
});

it('rejects an incomplete selected source before withdrawing or calling the gateway', function () {
    $gateway = Mockery::mock(GatewayInterface::class);
    $wallet = Mockery::mock(Wallet::class);

    expect(fn () => (new OmnipayBridge($gateway))->disburse($wallet, [
        'reference' => 'test-ref', 'amount' => 10, 'account_number' => '09170000000',
        'bank' => 'GXCHPHM2XXX', 'via' => 'INSTAPAY',
        'source_account' => ['provider' => 'netbank', 'account_number' => '991100001234'],
    ]))->toThrow(InvalidArgumentException::class, 'selected payout source is unavailable');
});
