<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway as NetbankGateway;
use LBHurtado\PaymentGateway\Omnipay\Support\OmnipayFactory;
use LBHurtado\PaymentGateway\Tests\TestCase;
use Omnipay\Common\GatewayInterface;

class OmnipayFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Provide fake config values for standalone package testing (no .env)
        config()->set('omnipay.gateways.netbank.options.clientId', 'test-client-id');
        config()->set('omnipay.gateways.netbank.options.clientSecret', 'test-client-secret');
        config()->set('omnipay.gateways.netbank.options.tokenEndpoint', 'https://fake-api.test/token');
    }
    public function test_creates_gateway_from_config()
    {
        $gateway = OmnipayFactory::create('netbank');

        $this->assertInstanceOf(GatewayInterface::class, $gateway);
        $this->assertInstanceOf(NetbankGateway::class, $gateway);
    }

    public function test_initializes_gateway_with_options()
    {
        $gateway = OmnipayFactory::create('netbank');

        // Verify gateway was initialized with config options from omnipay.php
        $this->assertNotEmpty($gateway->getClientId());
        $this->assertNotEmpty($gateway->getClientSecret());
    }

    public function test_throws_exception_for_nonexistent_gateway()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Gateway 'nonexistent' not found in omnipay config");

        OmnipayFactory::create('nonexistent');
    }

    public function test_create_default_returns_default_gateway()
    {
        $gateway = OmnipayFactory::createDefault();

        $this->assertInstanceOf(GatewayInterface::class, $gateway);
        $this->assertInstanceOf(NetbankGateway::class, $gateway);
    }

    public function test_available_returns_gateway_names()
    {
        $available = OmnipayFactory::available();

        $this->assertIsArray($available);
        $this->assertContains('netbank', $available);
        $this->assertContains('icash', $available);
    }

    public function test_has_returns_true_for_existing_gateway()
    {
        $result = OmnipayFactory::has('netbank');

        $this->assertTrue($result);
    }

    public function test_has_returns_false_for_nonexistent_gateway()
    {
        $result = OmnipayFactory::has('nonexistent');

        $this->assertFalse($result);
    }
}
