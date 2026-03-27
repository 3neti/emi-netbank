<?php

use Bavix\Wallet\Interfaces\Wallet;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Support\Facades\Config;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use LBHurtado\PaymentGateway\Services\ResolvePayable;
use LBHurtado\PaymentGateway\Tests\Models\User;
use LBHurtado\Voucher\Models\Voucher;

beforeEach(function () {
    // wire up our configs
    Config::set('payment-gateway.models.user', User::class);
    Config::set('payment.models.voucher', [
        'class' => Voucher::class,
        'field' => 'code',
    ]);
});

it('resolves a User wallet when mobile matches', function () {
    $user = User::factory()->create();
    $user->mobile = '09170000001';
    $user->save();

    // pretend this is the incoming "91500:09170000001"
    $dto = new RecipientAccountNumberData('91500', '09170000001');

    $wallet = app(ResolvePayable::class)
        ->execute($dto);

    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->is($user))->toBeTrue();
});

it('resolves a Voucher cash wallet when code matches', function () {
    // create a voucher + cash
    $voucher = Vouchers::create();

    $cash = Cash::factory()->create([
        'amount' => 1500.00,
        'currency' => 'PHP',
        'meta' => ['note' => 'Disbursed for transport support'],
    ]);
    $entities = compact('cash');

    $voucher->addEntities(...$entities);
    $voucher->refresh();

    $dto = RecipientAccountNumberData::fromRecipientAccountNumber('91500'.$voucher->code);

    $wallet = app(ResolvePayable::class)
        ->execute($dto);

    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->getKey())->toBe($cash->getKey());
})->skip("no voucher here");

it('throws if neither user nor voucher found', function () {
    $dto = new RecipientAccountNumberData('91500', 'NOTEXIST');
    app(ResolvePayable::class)->execute($dto);
})->throws(\RuntimeException::class)->skip("no voucher here");;
