<?php

use LBHurtado\EmiCore\Contracts\BankRegistryContract;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway;

it('uses supplied sender and recipient addresses without generating replacements', function (): void {
    $banks = Mockery::mock(BankRegistryContract::class);
    $banks->shouldReceive('supportsRail')->andReturnTrue();
    app()->instance(BankRegistryContract::class, $banks);
    $gateway = new Gateway;
    $gateway->setRails(['INSTAPAY' => ['enabled' => true]]);
    $sender = ['name' => 'Issuer', 'address' => ['address1' => 'Issuer locality', 'city' => 'Manila', 'country' => 'PH', 'postal_code' => '1000']];
    $recipient = ['name' => 'Recipient', 'address' => ['address1' => 'Recipient locality', 'city' => 'Cebu City', 'country' => 'PH', 'postal_code' => '6000']];
    $request = $gateway->disburse(['amount' => 10000, 'accountNumber' => '09170000000', 'bankCode' => 'GXCHPHM2XXX', 'reference' => 'test-ref', 'via' => 'INSTAPAY', 'senderParty' => $sender, 'recipientParty' => $recipient]);
    $payload = $request->getData();

    expect($payload['sender']['address'])->toBe($sender['address'])
        ->and($payload['recipient']['address'])->toBe($recipient['address'])
        ->and($payload['recipient']['name'])->toBe('Recipient');
});

it('rejects supplied addresses exceeding the provider limit without truncation', function (): void {
    $banks = Mockery::mock(BankRegistryContract::class);
    $banks->shouldReceive('supportsRail')->andReturnTrue();
    app()->instance(BankRegistryContract::class, $banks);
    $gateway = new Gateway;
    $gateway->setRails(['INSTAPAY' => ['enabled' => true]]);
    $party = ['address' => ['address1' => str_repeat('ñ', 129), 'city' => 'Manila', 'country' => 'PH', 'postal_code' => '1000']];
    $request = $gateway->disburse(['amount' => 10000, 'accountNumber' => '09170000000', 'bankCode' => 'GXCHPHM2XXX', 'reference' => 'test-ref', 'via' => 'INSTAPAY', 'senderParty' => $party, 'recipientParty' => $party]);
    expect(fn () => $request->getData())->toThrow(InvalidArgumentException::class, 'address limit');
});

it('does not replace an explicitly supplied but incomplete party with a synthetic address', function (): void {
    $banks = Mockery::mock(BankRegistryContract::class);
    $banks->shouldReceive('supportsRail')->andReturnTrue();
    app()->instance(BankRegistryContract::class, $banks);
    $gateway = new Gateway;
    $gateway->setRails(['INSTAPAY' => ['enabled' => true]]);
    $request = $gateway->disburse(['amount' => 10000, 'accountNumber' => '09170000000', 'bankCode' => 'GXCHPHM2XXX', 'reference' => 'test-ref', 'via' => 'INSTAPAY', 'senderParty' => ['name' => 'Issuer']]);
    expect(fn () => $request->getData())->toThrow(InvalidArgumentException::class, 'incomplete');
});
