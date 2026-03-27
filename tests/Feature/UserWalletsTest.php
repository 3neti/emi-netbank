<?php

use LBHurtado\PaymentGateway\Tests\Models\User;
use LBHurtado\Wallet\Enums\WalletType;
use LBHurtado\Wallet\Services\WalletProvisioningService;

it('creates wallets for all WalletType enums upon user creation', function () {
    $user = User::factory()->create();

    foreach (WalletType::cases() as $type) {
        $wallet = $user->getWallet($type->value);
        expect($wallet)->not->toBeNull()
            ->and($wallet->slug)->toBe($type->value)
            ->and($wallet->name)->toBe($type->label())
            ->and($wallet->holder_id)->toBe($user->getKey());
    }
});

it('sets wallet balances to zero by default', function () {
    $user = User::factory()->create();

    foreach (WalletType::cases() as $type) {
        $wallet = $user->getWallet($type->value);

        expect((float) $wallet->balanceFloat)->toBe(0.0)
            ->and($wallet->meta)->toBeArray();
    }
});

// use LBHurtado\PaymentGateway\Support\WalletConfig;
//
// it('sets default metadata on each wallet', function () {
//    $user = User::factory()->create();
//
//    foreach (WalletType::cases() as $type) {
//        $wallet = $user->getWallet($type->value);
//
//        $expectedMeta = WalletConfig::defaultMeta($type); // e.g., []
//        expect($wallet->meta)->toBe($expectedMeta);
//    }
// });

it('does not duplicate wallets on repeated provisioning', function () {
    $user = User::factory()->create();
    $walletService = app(WalletProvisioningService::class);

    $walletService->createDefaultWalletsForUser($user);
    $walletService->createDefaultWalletsForUser($user);

    foreach (WalletType::cases() as $type) {
        $wallets = $user->wallets()->where('slug', $type->value)->get();
        expect($wallets)->toHaveCount(1);
    }
});
