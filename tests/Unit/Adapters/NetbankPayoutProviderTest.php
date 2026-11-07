<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Wallet;
use LBHurtado\EmiCore\Contracts\PayoutIdentityRequirementsProvider;
use LBHurtado\EmiCore\Data\PayoutAddressData;
use LBHurtado\EmiCore\Data\PayoutPartyData;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;

it('advertises NetBank payout address requirements as an optional capability', function (): void {
    $provider = new NetbankPayoutProvider(Mockery::mock(PaymentGatewayInterface::class), Mockery::mock(WalletProxy::class));
    expect($provider)->toBeInstanceOf(PayoutIdentityRequirementsProvider::class)
        ->and($provider->payoutIdentityRequirements('INSTAPAY')->address1_max_length)->toBe(128)
        ->and($provider->payoutIdentityRequirements('INSTAPAY')->required_address_fields)->toBe(['address1', 'city', 'country', 'postal_code']);
});

it('passes both resolved parties through the payout adapter', function (): void {
    $wallet = Mockery::mock(Wallet::class);
    $wallets = Mockery::mock(WalletProxy::class);
    $wallets->shouldReceive('resolve')->once()->andReturn($wallet);
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('disburse')->once()->with($wallet, Mockery::on(function (DisburseInputData $data): bool {
        return data_get($data->toArray(), 'sender.address.address1') === 'Issuer locality'
            && data_get($data->toArray(), 'recipient.address.address1') === 'Recipient locality';
    }))->andReturnFalse();
    $provider = new NetbankPayoutProvider($gateway, $wallets);
    $provider->disburse(new PayoutRequestData(
        'ref-1', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY',
        sender: new PayoutPartyData('sender', 'Issuer', new PayoutAddressData('Issuer locality', 'Manila', 'PH', '1000', 'configured')),
        recipient: new PayoutPartyData('recipient', 'Recipient', new PayoutAddressData('Recipient locality', 'Cebu City', 'PH', '6000', 'ip_assisted_synthetic')),
    ));
});

it('omits party identity from the legacy gateway audit payload', function (): void {
    $data = DisburseInputData::from([
        'reference' => 'ref-audit', 'amount' => 100, 'account_number' => '09170000000',
        'bank' => 'GXCHPHM2XXX', 'via' => 'INSTAPAY',
        'sender' => ['role' => 'sender', 'name' => 'Private Sender', 'address' => ['address1' => 'Private Locality', 'city' => 'Manila', 'country' => 'PH', 'postal_code' => '1000', 'source' => 'synthetic']],
    ]);
    $audit = $data->toAuditArray();
    expect($audit)->not->toHaveKey('sender')->not->toHaveKey('recipient')
        ->and(json_encode($audit))->not->toContain('Private Sender')->not->toContain('Private Locality');
});

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
