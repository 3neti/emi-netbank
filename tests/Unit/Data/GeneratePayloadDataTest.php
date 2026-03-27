<?php

use Brick\Money\Money;
use Illuminate\Support\Facades\Config;
use LBHurtado\Merchant\Models\Merchant;
use LBHurtado\PaymentGateway\Data\Netbank\Generate\GeneratePayloadData;
use LBHurtado\PaymentGateway\Tests\Models\User;

beforeEach(function () {
    Config::set('disbursement.client.alias', '31799');
});

it('formats destination_account correctly when merchant code is provided', function () {
    $merchant = Merchant::factory()->create([
        'name' => 'ACME Corp',
        'city' => 'Makati City',
        'code' => 'LBH',
    ]);
    $user = User::factory()->create();
    $user->merchant = $merchant;
    $user->save();

    $account = '09171234567';
    $amount = Money::of(100, 'PHP');

    $payload = GeneratePayloadData::fromUserAccountAmount($user, $account, $amount);

    // Expect: alias + the account
    expect($payload->destination_account)
        ->toBe('3179909171234567');
});
