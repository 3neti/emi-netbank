<?php

namespace LBHurtado\PaymentGateway\Services;

use Illuminate\Support\Manager;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;
use LBHurtado\PaymentGateway\Omnipay\Support\OmnipayFactory;

class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('payment-gateway.default', 'netbank');
    }

    public function createNetbankDriver(): PaymentGatewayInterface
    {
        // Check if Omnipay should be used
        if (config('omnipay.use_omnipay', false)) {
            return new OmnipayBridge(
                OmnipayFactory::create('netbank')
            );
        }

        // Use legacy implementation
        return new NetbankPaymentGateway;
    }

    public function createIcashDriver(): PaymentGatewayInterface
    {
        // Check if Omnipay should be used
        if (config('omnipay.use_omnipay', false)) {
            return new OmnipayBridge(
                OmnipayFactory::create('icash')
            );
        }

        // ICash legacy driver not implemented
        throw new \RuntimeException('iCash driver not implemented yet.');
    }

    /**
     * Create an Omnipay-based driver
     *
     * @param  string  $gateway  Gateway name from omnipay config
     */
    public function createOmnipayDriver(string $gateway = 'netbank'): PaymentGatewayInterface
    {
        return new OmnipayBridge(
            OmnipayFactory::create($gateway)
        );
    }
}
