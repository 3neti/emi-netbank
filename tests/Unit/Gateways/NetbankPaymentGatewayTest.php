<?php

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transaction;
use Brick\Money\Money;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LBHurtado\Merchant\Models\Merchant;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositMerchantDetailsData;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositResponseData;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositSenderData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;
use LBHurtado\PaymentGateway\Tests\Models\User;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Wallet\Events\DepositConfirmed;
use LBHurtado\Wallet\Events\DisbursementConfirmed;
use LBHurtado\Wallet\Services\SystemUserResolverService;

beforeEach(function () {
    Config::set('account.system_user.identifier', 'system@dev-asiana.io');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);

    // Provide fake config values for standalone package testing (no .env)
    Config::set('disbursement.server.end-point', 'https://fake-api.test/disburse');
    Config::set('disbursement.server.token-end-point', 'https://fake-api.test/token');
    Config::set('disbursement.server.qr-end-point', 'https://fake-api.test/qr');
    Config::set('disbursement.client.id', 'test-client-id');
    Config::set('disbursement.client.secret', 'test-client-secret');
    Config::set('disbursement.client.alias', '91500');

    // Seed or ensure a system user exists
    $system = User::factory()->create([
        'name' => 'System',
        'email' => 'system@dev-asiana.io',
    ]);
    $system->wallet; // Explicitly create the wallet
    $system->depositFloat(10_000.00); // Start with a balance of 10,000
});

it('tests the NetbankPaymentGateway generate method', function () {
    // Assume you already have a user with a merchant in your database
    $user = auth()->user(); // The currently authenticated user with a merchant relationship

    // Ensure the user model has the expected merchant relationship
    expect($user->merchant)->not->toBeNull();
    expect($user->merchant->name)->toBe('Test Merchant');
    expect($user->merchant->city)->toBe('Test City');
    //    expect($user->merchant->code)->toBe('123ABC'); // Assumes you store the merchant code here

    // Fake HTTP responses for token and QR code endpoints
    Http::fake([
        // Fake token response
        config('disbursement.server.token-end-point') => Http::response([
            'access_token' => 'fake-access-token',
        ], 200),

        // Fake QR code response
        config('disbursement.server.qr-end-point') => Http::response([
            'qr_code' => base64_encode('sample-qr-code-data'),
        ], 200),
    ]);

    // Create an instance of NetbankPaymentGateway
    $gateway = new NetbankPaymentGateway;

    // Define the transaction amount for generating the QR code
    $amount = Money::of(1_000, 'PHP'); // Example amount: 1000 PHP

    // Call the generate method to get the QR code response
    $response = $gateway->generate('1234567890', $amount);

    // Assertions for the QR code response
    expect($response)->toStartWith('data:image/png;base64,'); // Ensure it starts with a base64 encoded prefix

    // Assert the token endpoint was called correctly
    Http::assertSent(function ($request) {
        return $request->url() === config('disbursement.server.token-end-point') &&
            $request->hasHeader(
                'Authorization',
                'Basic '.base64_encode(config('disbursement.client.id').':'.config('disbursement.client.secret'))
            );
    });

    //    // Assert the QR code endpoint was called correctly with the appropriate payload
    //    Http::assertSent(function ($request) use ($user, $amount) {
    //        $payload = $request->data();
    //        return $request->url() === config('disbursement.server.qr-end-point') &&
    //            $payload['merchant_name'] === $user->merchant->name &&
    //            $payload['merchant_city'] === $user->merchant->city &&
    //            $payload['qr_type'] === ($amount->isZero() ? 'Static' : 'Dynamic') &&
    //            $payload['qr_transaction_type'] === 'P2M' &&
    //            $payload['destination_account'] === __(':alias:account', [
    //                'alias' => config('disbursement.client.alias'),
    //                'account' => $user->merchant->code, // Use real merchant code here
    //            ]);
    //    });
});

it('sends a live QR transaction to Netbank', function () {
    // Fetch the authenticated user (ensure user is set up correctly)
    $user = auth()->user();

    // Ensure the user has a corresponding merchant relationship
    expect($user->merchant)->not->toBeNull();
    expect($user->merchant->name)->not->toBeNull();
    expect($user->merchant->city)->not->toBeNull();

    // Create an instance of NetbankPaymentGateway
    $gateway = new NetbankPaymentGateway;

    // Define the transaction amount (adjust as needed)
    $amount = Money::of(1_000, 'PHP'); // Example amount: 1000 PHP

    try {
        // Call the generate method to send the transaction
        $response = $gateway->generate('1234567890', $amount);

        // Log the response for debugging
        dump('QR Code Response:', $response);

        // Assert that it returns a valid base64-encoded QR code string
        expect($response)->toStartWith('data:image/png;base64,');

    } catch (\Throwable $e) {
        // Catch and log any errors returned by Netbank or the gateway
        dump('Error:', $e->getMessage());
        throw $e; // Rethrow the exception if needed for assertion purposes
    }
})->skip();

dataset('user', function () {
    return [
        [fn () => tap(auth()->user(), function (User $user) {
            $user->mobile = '09173011987';
            $user->merchant = Merchant::factory()->create(['code' => 1]);
            $user->save();
            $user->wallet; // Explicitly create the wallet
            $user->wallet->refreshBalance();
        })],
    ];
});

dataset('response', function () {
    return [
        [fn () => new DepositResponseData(
            alias: 'TEST_ALIAS',
            amount: 1_000, // Deposit amount
            channel: 'TestChannel',
            commandId: 123,// 'COMMAND123',
            externalTransferStatus: 'SUCCESS',
            operationId: 456,// 'OPID-456',
            productBranchCode: '1010',
            recipientAccountNumber: '09181234567',
            recipientAccountNumberBankFormat: '09181234567',
            referenceCode: '09171234567', // Matches the user's mobile
            referenceNumber: 'REFNO123',
            registrationTime: '2023-09-27 12:00:00',
            remarks: 'Transaction Remark',
            sender: new DepositSenderData(
                accountNumber: '123456789',
                institutionCode: 'NBANK',
                name: 'Test Sender'
            ),
            transferType: 'P2M',
            merchant_details: new DepositMerchantDetailsData(
                merchant_code: 'A',
                merchant_account: '09171234567'
            )
        )],
    ];
});

dataset('live_response', function () {
    return [
        [fn () => new DepositResponseData(
            alias: '91500',
            amount: 150,
            channel: 'INSTAPAY',
            commandId: 140787329,
            externalTransferStatus: 'SETTLED',
            operationId: 175192002,
            productBranchCode: '000',
            recipientAccountNumber: '9150009173011987',
            recipientAccountNumberBankFormat: '113-001-00001-9',
            referenceCode: '19173011987',
            referenceNumber: '20250613GXCHPHM2XXXB000000005603644',
            registrationTime: '2025-06-13T19:31:01.607',
            remarks: 'InstaPay transfer #20250613GXCHPHM2XXXB000000005603644',
            sender: new DepositSenderData(
                accountNumber: '09175180722',
                institutionCode: 'GXCHPHM2XXX',
                name: 'RUTH APPLE HURTADO'
            ),
            transferType: 'QR_P2M',
            merchant_details: new DepositMerchantDetailsData(
                merchant_code: '1',
                merchant_account: '09173011987'
            )
        )],
    ];
});

it('tests confirmDeposit function in NetbankPaymentGateway', function (User $user, DepositResponseData $response) {
    // Arrange
    Event::fake();
    Log::spy();

    // Mock the Transfer instance
    $transfer = \Mockery::mock(\Bavix\Wallet\Models\Transfer::class)->makePartial();

    // Mock the Transaction (deposit) relationship and its attributes
    $transaction = \Mockery::mock(\Bavix\Wallet\Models\Transaction::class);
    $transaction->shouldReceive('getAttribute')
        ->with('payable')
        ->andReturn($user) // Set the transaction's payable relationship to the user
        ->shouldReceive('getAttribute')
        ->with('amount')
        ->andReturn(150 * 100); // Set the transaction's amount in minor units

    // Optional: Add other attributes if needed
    $transaction->shouldReceive('getAttribute')
        ->with('type')
        ->andReturn('deposit');

    // Inject the mocked transaction into the transfer
    $transfer->setRelation('deposit', $transaction); // Properly mock the deposit relationship

    // Mock the Wallet Action
    TopupWalletAction::shouldRun()
        ->with($user, $response->amount)
        ->andReturn($transfer); // Return the mocked transfer

    $gateway = new NetbankPaymentGateway;

    // Act
    $result = $gateway->confirmDeposit($response->toArray()); // Call the method under test

    // Assert
    expect($result)->toBeTrue(); // Ensure the action returns true

    // Assert the DepositConfirmed event was dispatched with the correct transaction
    Event::assertDispatched(DepositConfirmed::class, function (DepositConfirmed $event) use ($transaction) {
        return
            $event->transaction->getAttribute('payable')->is($transaction->getAttribute('payable'))
            && $event->transaction->getAttribute('amount') === $transaction->getAttribute('amount')
            && $event->transaction->getAttribute('type') === $transaction->getAttribute('type'); // Compare against the actual mocked transaction
    });

    // (Optional) Verify logging
    Log::shouldHaveReceived('info')->twice();
})->with('user', 'live_response');

it('tests confirmDeposit with valid payload and updates balances', function (User $user, DepositResponseData $response) {
    // Arrange
    $system = app(SystemUserResolverService::class)->resolve();
    $system->wallet->refreshBalance();
    $this->withoutExceptionHandling(); // For easier debugging during testing

    expect((float) $user->balanceFloat)->toBe(0.00);

    // Mock the gateway BUT DO NOT mock transferToWallet
    $gateway = new NetbankPaymentGateway; // No Mockery here; use the actual implementation

    // Act: Call confirmDeposit to test the full integration
    $result = $gateway->confirmDeposit($response->toArray());

    // Assert
    expect($result)->toBeTrue(); // Ensure the confirmDeposit returned true
    $system->wallet->refreshBalance();
    expect((float) $system->balanceFloat)->toBe(9_850.00); // System loses 1500
    $user->wallet->refreshBalance();
    expect((float) $user->balanceFloat)->toBe(150.00); // User gains 1,000
})->with('user', 'live_response');

dataset('disbursement', function () {
    return [
        [fn () => [
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
            'amount' => 1_000,
            'bank' => 'ALKBPHM2XXX',
            'account_number' => '1234567890',
        ]],
    ];
});

it('successfully disburses funds to a bank account', function (User $user, array $validated) {
    // Arrange
    $this->withoutExceptionHandling();

    $system = app(SystemUserResolverService::class)->resolve();
    $user = auth()->user();
    expect($user instanceof User)->toBeTrue();
    expect($user instanceof Wallet)->toBeTrue();

    expect((float) $system->balanceFloat)->toBe(10_000.00);
    expect((float) $user->balanceFloat)->toBe(0.00);

    TopupWalletAction::run($user, 3_000.00);
    $system->wallet->refreshBalance();
    expect((float) $system->balanceFloat)->toBe(7_000.00);
    $user->wallet->refreshBalance();
    expect((float) $user->balanceFloat)->toBe(3_000.00);

    Http::fake([
        config('disbursement.server.token-end-point') => Http::response(['access_token' => 'fake-token'], 200),
        config('disbursement.server.end-point') => Http::response([
            'transaction_id' => 'TXN-987654',
            'status' => 'PENDING',
        ], 200),
    ]);

    Log::spy();

    $gateway = new NetbankPaymentGateway;

    $data = DisburseInputData::from($validated);

    // Act
    $result = $gateway->disburse($user, $data);

    // Assert
    expect($result)->toBeInstanceOf(DisburseResponseData::class);
    expect($result->transaction_id)->toBe('TXN-987654');
    expect($result->status)->toBe('PENDING');

    $user->wallet->refreshBalance();
    expect((float) $user->balanceFloat)->toBe(3_000.00)
        ->and($result->uuid)->not->toBeEmpty();

    $operationId = $result->transaction_id;
    $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)->firstOrFail();

    expect($transaction->confirmed)->toBeFalse();
    $payable = $transaction->payable;
    $payable->confirm($transaction);

    expect($transaction->confirmed)->toBeTrue();
    $user->wallet->refreshBalance();
    expect((float) $user->balanceFloat)->toBe(2000.00);

    Http::assertSent(fn ($request) => $request->url() === config('disbursement.server.end-point') &&
        $request->hasHeader('Authorization', 'Bearer fake-token') &&
        $request['reference_id'] === $validated['reference']
    );

    Log::shouldHaveReceived('info')->with('NetbankPaymentGateway@disburse', \Mockery::any());
})->with('user', 'disbursement')->skip();

it('confirms disbursement and deducts from user wallet', function (User $user, array $validated) {
    // Arrange
    Event::fake();
    $this->withoutExceptionHandling();

    $system = app(SystemUserResolverService::class)->resolve();
    $system->wallet->refreshBalance();
    $user->wallet->refreshBalance();

    expect((float) $user->balanceFloat)->toBe(0.00);
    TopupWalletAction::run($user, 3_000.00);
    $user->wallet->refreshBalance();
    expect((float) $user->balanceFloat)->toBe(3_000.00);

    Http::fake([
        config('disbursement.server.token-end-point') => Http::response(['access_token' => 'fake-token'], 200),
        config('disbursement.server.end-point') => Http::response([
            'transaction_id' => 'CONFIRM-TXN-001',
            'status' => 'PENDING',
        ], 200),
    ]);

    $gateway = new NetbankPaymentGateway;
    $response = $gateway->disburse($user, $validated);
    $operationId = $response->transaction_id;

    $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)->firstOrFail();
    expect($transaction->confirmed)->toBeFalse();

    // Act
    $result = $gateway->confirmDisbursement($operationId);

    // Assert
    expect($result)->toBeTrue();

    $transaction->refresh();
    expect($transaction->confirmed)->toBeTrue();

    $user->wallet->refreshBalance();
    expect((float) $user->balanceFloat)->toBe(2_000.00); // Wallet deducted upon confirmation

    Event::assertDispatched(DisbursementConfirmed::class, function (DisbursementConfirmed $event) use ($transaction) {
        return $event->transaction->is($transaction);
    });
})->with('user', 'disbursement')->skip();

it('sends a live disbursement transaction to Netbank', function () {
    // Fetch the authenticated user (ensure a valid user is set up in the test environment)
    $user = auth()->user();
    TopupWalletAction::run($user, 3_000.00);

    // Ensure the user exists
    expect($user)->not->toBeNull();
    expect($user->name)->not->toBeNull();
    expect($user->email)->not->toBeNull();

    // Define the validated input (disbursement data)
    //    $validated = [
    //        'reference'         => Str::random(4) . '-09173011987',  // Unique reference for this transaction
    //        'amount'            => 53.17,                            // Transaction amount
    //        'account_number'    => '09173011987',                    // Destination bank account
    //        'bank'              => 'GXCHPHM2XXX',                    // GCash Bank Code
    //        'via'               => 'INSTAPAY'
    //    ];

    $validated = [
        'reference' => Str::random(4).'-09173011987',  // Unique reference for this transaction
        'amount' => 53.17,                            // Transaction amount
        'account_number' => '09173011987',                    // Destination bank account
        'bank' => 'PAPHPHM1XXX',                    // Maya Bank Code
        'via' => 'INSTAPAY',
    ];

    // Create an instance of NetbankPaymentGateway
    $gateway = new NetbankPaymentGateway;

    try {
        // Send the live disbursement request
        $response = $gateway->disburse($user, $validated);

        // Log the response for debugging purposes
        dump('Disbursement Response:', $response);

        // Assert the response is valid and contains expected keys
        expect($response)->toBeInstanceOf(DisburseResponseData::class);
        expect($response->transaction_id)->not->toBeNull();
        //        expect($response->status)->toBeOneOf(['PENDING', 'SUCCESS']);

    } catch (\Throwable $e) {
        // Catch and log any potential errors returned by Netbank or the gateway
        dump('Error:', $e->getMessage());
        throw $e; // Rethrow the exception for assertion purposes
    }
})->skip();
