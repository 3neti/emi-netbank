<?php

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\PaymentGateway\Contracts\WalletResolver;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;

it('has WalletResolver contract interface', function () {
    $reflection = new ReflectionClass(WalletResolver::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->hasMethod('resolve'))->toBeTrue();
});

it('WalletResolver can be bound and resolved', function () {
    $wallet = Mockery::mock(Wallet::class)->shouldIgnoreMissing();

    $this->app->instance(WalletResolver::class, new class($wallet) implements WalletResolver
    {
        public function __construct(private $wallet) {}

        public function resolve(RecipientAccountNumberData $data): Wallet
        {
            return $this->wallet;
        }
    });

    expect(app(WalletResolver::class))->toBeInstanceOf(WalletResolver::class);
});
