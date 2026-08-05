<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;

it('maps settled NetBank status to completed payout status', function () {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkDisbursementStatus')
        ->once()
        ->with('407907009')
        ->andReturn([
            'status' => 'completed',
            'raw' => [
                'transaction_id' => '407907009',
                'status' => 'Settled',
                'operation_id' => '407907009',
                'reference_number' => '202621100089894',
            ],
        ]);

    $provider = new NetbankPayoutProvider(
        $gateway,
        Mockery::mock(WalletProxy::class),
    );

    $result = $provider->checkStatus('407907009');

    expect($result->status)->toBe(PayoutStatus::COMPLETED)
        ->and($result->metadata['status'])->toBe('Settled')
        ->and($result->metadata['operation_id'])->toBe('407907009');
});

it('maps rejected NetBank status to failed payout status with rejection reason', function () {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('checkDisbursementStatus')
        ->once()
        ->with('407906626')
        ->andReturn([
            'status' => 'failed',
            'raw' => [
                'transaction_id' => '407906626',
                'status' => 'Rejected',
                'operation_id' => '407906626',
                'status_details' => [
                    ['status' => 'Pending', 'updated' => '2026-07-30T03:19:02Z'],
                    ['status' => 'Rejected', 'message' => 'AC06 (Blocked account) ', 'updated' => '2026-07-30T03:19:03Z'],
                ],
            ],
        ]);

    $provider = new NetbankPayoutProvider(
        $gateway,
        Mockery::mock(WalletProxy::class),
    );

    $result = $provider->checkStatus('407906626');

    expect($result->status)->toBe(PayoutStatus::FAILED)
        ->and($result->metadata['status'])->toBe('Rejected')
        ->and($result->metadata['rejection_reason'])->toBe('AC06 (Blocked account)');
});

it('marks a rejected submission as never accepted by NetBank', function (): void {
    $wallet = Mockery::mock(Wallet::class);
    $wallets = Mockery::mock(WalletProxy::class);
    $wallets->shouldReceive('resolve')->once()->andReturn($wallet);
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('disburse')
        ->once()
        ->with($wallet, Mockery::type(DisburseInputData::class))
        ->andReturnFalse();
    $provider = new NetbankPayoutProvider($gateway, $wallets);

    $result = $provider->disburse(new PayoutRequestData(
        reference: 'F6BG-R2',
        amount: 1_000,
        account_number: '09853353980',
        bank_code: 'GXCHPHM2XXX',
        settlement_rail: 'INSTAPAY',
    ));

    expect($result->status)->toBe(PayoutStatus::FAILED)
        ->and($result->transaction_id)->toBe('F6BG-R2')
        ->and($result->metadata)->toMatchArray([
            'provider_submission_accepted' => false,
            'failure_phase' => 'provider_submission',
            'failure_code' => 'submission_not_accepted',
        ]);
});
