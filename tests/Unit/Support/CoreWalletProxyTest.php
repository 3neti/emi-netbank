<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\EmiCore\Contracts\ProviderWalletResolver;
use LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use LBHurtado\PaymentGateway\PaymentGatewayServiceProvider;

it('bridges the core wallet resolver for the existing NetBank payout adapter', function (): void {
    $wallet = Mockery::mock(Wallet::class);
    $resolver = Mockery::mock(ProviderWalletResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($wallet);
    $this->app->instance(ProviderWalletResolver::class, $resolver);

    expect(app(WalletProxy::class)->resolve())->toBe($wallet)
        ->and(config('emi.payout_providers.netbank'))->toBe(NetbankPayoutProvider::class);
});

it('rejects an unsupported provider wallet', function (): void {
    $resolver = Mockery::mock(ProviderWalletResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn(new stdClass);
    $this->app->instance(ProviderWalletResolver::class, $resolver);

    expect(fn () => app(WalletProxy::class)->resolve())->toThrow(RuntimeException::class);
});

it('preserves an existing host wallet proxy and payout provider alias', function (): void {
    $legacy = Mockery::mock(WalletProxy::class);
    $this->app->instance(WalletProxy::class, $legacy);
    config()->set('emi.payout_providers.netbank', 'host-custom-provider');

    (new PaymentGatewayServiceProvider($this->app))->register();

    expect(app(WalletProxy::class))->toBe($legacy)
        ->and(config('emi.payout_providers.netbank'))->toBe('host-custom-provider');
});
