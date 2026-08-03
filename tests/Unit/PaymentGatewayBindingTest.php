<?php

declare(strict_types=1);

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;
use LBHurtado\PaymentGateway\Gateways\Omnipay\OmnipayPaymentGateway;

it('uses the provider-only gateway by default', function (): void {
    config()->set('omnipay.use_omnipay', true);

    expect(app(PaymentGatewayInterface::class))
        ->toBeInstanceOf(OmnipayPaymentGateway::class);
});

it('allows an explicit controlled rollback to the legacy gateway', function (): void {
    config()->set('omnipay.use_omnipay', false);

    expect(app(PaymentGatewayInterface::class))
        ->toBeInstanceOf(NetbankPaymentGateway::class);
});
