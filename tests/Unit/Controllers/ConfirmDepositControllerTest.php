<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

it('tests confirm-deposit controller without irrelevant implementation details', function () {
    // Fake events
    Event::fake();

    // Mock gateway
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $this->app->instance(PaymentGatewayInterface::class, $gateway);

    // Define payload
    $payload = [
        'alias' => 'TEST_ALIAS',
        'amount' => 1000,
        'channel' => 'TestChannel',
        'commandId' => 12345,
        'externalTransferStatus' => 'SUCCESS',
        'operationId' => 67890,
        'productBranchCode' => '1010',
        'recipientAccountNumber' => '09181234567',
        'recipientAccountNumberBankFormat' => '09181234567',
        'referenceCode' => '09171234567',
        'referenceNumber' => 'REF12345',
        'registrationTime' => '2023-09-27 12:00:00',
        'remarks' => 'Some remarks',
        'sender' => [
            'accountNumber' => '123456789',
            'institutionCode' => 'NBANK',
            'name' => 'Test Sender',
        ],
        'transferType' => 'P2M',
        'merchant_details' => [
            'merchant_code' => 'A',
            'merchant_account' => '09171234567',
        ],
    ];

    // Mock `confirmDeposit` and ensure it’s called
    $gateway->shouldReceive('confirmDeposit')
        ->once()
        ->with(Mockery::on(fn ($validated) => $validated === $payload))
        ->andReturn(true); // Just return success

    // Act
    $response = $this->postJson(route('confirm-deposit'), $payload);

    // Assert response status
    $response->assertNoContent();
})->skip();

it('handles the real‐world bank payload in ConfirmDepositController', function () {
    Event::fake();

    // 1️⃣ Bind a mock gateway
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $this->app->instance(PaymentGatewayInterface::class, $gateway);

    // 2️⃣ The exact payload your bank will send
    $payload = [
        'merchant_details' => [
            'merchant_code' => '1',
            'merchant_account' => '09173011987',
        ],
        'recipientAccountNumber' => '9150019173011987',
        'commandId' => 140787329,
        'operationId' => 175192002,
        'referenceNumber' => '20250613GXCHPHM2XXXB000000005603644',
        'sender' => [
            'name' => 'RUTH APPLE HURTADO',
            'accountNumber' => '09175180722',
            'institutionCode' => 'GXCHPHM2XXX',
        ],
        'alias' => '91500',
        'referenceCode' => '19173011987',
        'externalTransferStatus' => 'SETTLED',
        'remarks' => 'InstaPay transfer #20250613GXCHPHM2XXXB000000005603644',
        'amount' => 150,
        'registrationTime' => '2025-06-13T19:31:01.607',
        'transferType' => 'QR_P2M',
        'recipientAccountNumberBankFormat' => '113-001-00001-9',
        'channel' => 'INSTAPAY',
        'productBranchCode' => '000',
    ];

    //    $payload = [
    //        'alias' => '91500',
    //        'amount' => 150,
    //        'channel' => 'INSTAPAY',
    //        'commandId' => 140787329,
    //        'externalTransferStatus' => 'SETTLED',
    //        'operationId' => 175192002,
    //        'productBranchCode' => '000',
    //        'recipientAccountNumber' => '9150019173011987',
    //        'recipientAccountNumberBankFormat' => '113-001-00001-9',
    //        'referenceCode' => '19173011987',
    //        'referenceNumber' => '20250613GXCHPHM2XXXB000000005603644',
    //        'registrationTime' => '2025-06-13T19:31:01.607',
    //        'remarks' => 'InstaPay transfer #20250613GXCHPHM2XXXB000000005603644',
    //        'sender' => [
    //            'accountNumber' => '09175180722',
    //            'institutionCode' => 'GXCHPHM2XXX',
    //            'name' => 'RUTH APPLE HURTADO',
    //        ],
    //        'transferType' => 'QR_P2M',
    //        'merchant_details' => [
    //            'merchant_code' => '1',
    //            'merchant_account' => '09173011987',
    //        ],
    //    ];

    // 3️⃣ Expect the gateway to receive *exactly* that validated array
    $gateway
        ->shouldReceive('confirmDeposit')
        ->once()
        ->with(Mockery::on(function (array $validated) use ($payload) {
            // recursively sort both arrays by key…
            $a = Arr::sortRecursive($validated);
            $b = Arr::sortRecursive($payload);

            // …and only then compare
            return $a === $b;
        }))
        ->andReturnTrue();
    //    $gateway->shouldReceive('confirmDeposit')
    //        ->once()
    //        ->with(Mockery::on(fn ($validated) => $validated === $payload))
    //        ->andReturn(true); // Just return success

    // 4️⃣ Hit your endpoint
    $response = $this->postJson(route('confirm-deposit'), $payload);

    // 5️⃣ It should validate & return 204 No Content
    $response->assertNoContent();
});
