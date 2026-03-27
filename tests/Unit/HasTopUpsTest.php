<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\PaymentGateway\Exceptions\TopUpException;
use LBHurtado\PaymentGateway\Tests\Models\User;

beforeEach(function () {
    // Mock the NetBank Direct Checkout API
    Http::fake([
        'api.netbank.ph/v1/collect/checkout' => Http::response([
            'redirect_url' => 'https://checkout.netbank.ph/test-123',
            'reference_no' => 'TOPUP-TEST123',
        ], 200),
    ]);
});

test('user can initiate top-up via netbank', function () {
    $user = User::first();

    $result = $user->initiateTopUp(500, 'netbank', 'GCASH');

    expect($result)->toBeInstanceOf(\LBHurtado\PaymentGateway\Data\TopUp\TopUpResultData::class)
        ->and($result->gateway)->toBe('netbank')
        ->and($result->amount)->toBe(500.0)
        ->and($result->redirect_url)->toContain('checkout.netbank.ph');
});

test('top-up creates database record', function () {
    $user = User::first();

    $result = $user->initiateTopUp(1000, 'netbank');

    // Check database
    $this->assertDatabaseHas('top_ups', [
        'user_id' => $user->id,
        'gateway' => 'netbank',
        'amount' => 1000,
        'payment_status' => 'PENDING',
    ]);
});

test('user can retrieve pending top-ups', function () {
    $user = User::first();

    $user->initiateTopUp(500);
    $user->initiateTopUp(1000);

    $pending = $user->getPendingTopUps();

    expect($pending)->toHaveCount(2)
        ->and($pending->first()->payment_status)->toBe('PENDING');
});

test('user can get total top-ups amount', function () {
    $user = User::first();

    // Create paid top-ups
    $topUp1 = $user->topUps()->create([
        'gateway' => 'netbank',
        'reference_no' => 'REF-1',
        'amount' => 500,
        'currency' => 'PHP',
        'payment_status' => 'PAID',
        'redirect_url' => 'https://example.com',
    ]);

    $topUp2 = $user->topUps()->create([
        'gateway' => 'netbank',
        'reference_no' => 'REF-2',
        'amount' => 1000,
        'currency' => 'PHP',
        'payment_status' => 'PAID',
        'redirect_url' => 'https://example.com',
    ]);

    expect($user->getTotalTopUps())->toBe(1500.0);
});

test('throws exception for invalid amount', function () {
    $user = User::first();

    $user->initiateTopUp(100000); // Exceeds max
})->throws(TopUpException::class);

test('throws exception for unsupported gateway', function () {
    $user = User::first();

    $user->initiateTopUp(500, 'stripe'); // Not yet implemented
})->throws(TopUpException::class);

test('can get top-up by reference number', function () {
    $user = User::first();

    $result = $user->initiateTopUp(500);

    $topUp = $user->getTopUpByReference($result->reference_no);

    expect($topUp)->toBeInstanceOf(\LBHurtado\PaymentGateway\Contracts\TopUpInterface::class)
        ->and($topUp->getAmount())->toBe(500.0);
});

test('throws exception when reference not found', function () {
    $user = User::first();

    $user->getTopUpByReference('INVALID-REF');
})->throws(TopUpException::class);
