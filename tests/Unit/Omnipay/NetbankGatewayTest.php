<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway;
use PHPUnit\Framework\TestCase;

class NetbankGatewayTest extends TestCase
{
    protected Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway;
    }

    public function test_gateway_name()
    {
        $this->assertEquals('Netbank', $this->gateway->getName());
    }

    public function test_default_parameters()
    {
        $params = $this->gateway->getDefaultParameters();

        $this->assertArrayHasKey('clientId', $params);
        $this->assertArrayHasKey('clientSecret', $params);
        $this->assertArrayHasKey('tokenEndpoint', $params);
        $this->assertArrayHasKey('apiEndpoint', $params);
        $this->assertArrayHasKey('qrEndpoint', $params);
        $this->assertArrayHasKey('statusEndpoint', $params);
        $this->assertArrayHasKey('balanceEndpoint', $params);
        $this->assertArrayHasKey('testMode', $params);
        $this->assertArrayHasKey('rails', $params);
    }

    public function test_initialize_parameters()
    {
        $this->gateway->initialize([
            'clientId' => 'test-client',
            'clientSecret' => 'test-secret',
            'tokenEndpoint' => 'https://api.test.com/token',
            'apiEndpoint' => 'https://api.test.com/disburse',
        ]);

        $this->assertEquals('test-client', $this->gateway->getClientId());
        $this->assertEquals('test-secret', $this->gateway->getClientSecret());
        $this->assertEquals('https://api.test.com/token', $this->gateway->getTokenEndpoint());
        $this->assertEquals('https://api.test.com/disburse', $this->gateway->getApiEndpoint());
    }

    public function test_supports_rail_with_enabled_rail()
    {
        $this->gateway->setRails([
            'INSTAPAY' => ['enabled' => true],
            'PESONET' => ['enabled' => false],
        ]);

        $this->assertTrue($this->gateway->supportsRail(SettlementRail::INSTAPAY));
        $this->assertFalse($this->gateway->supportsRail(SettlementRail::PESONET));
    }

    public function test_get_rail_config()
    {
        $this->gateway->setRails([
            'INSTAPAY' => [
                'enabled' => true,
                'min_amount' => 1,
                'max_amount' => 50000 * 100,
                'fee' => 1000,
            ],
        ]);

        $config = $this->gateway->getRailConfig(SettlementRail::INSTAPAY);

        $this->assertIsArray($config);
        $this->assertEquals(true, $config['enabled']);
        $this->assertEquals(1, $config['min_amount']);
        $this->assertEquals(50000 * 100, $config['max_amount']);
        $this->assertEquals(1000, $config['fee']);
    }

    public function test_creates_disburse_request()
    {
        $request = $this->gateway->disburse([
            'amount' => 1000,
            'accountNumber' => '1234567890',
        ]);

        $this->assertInstanceOf(
            \LBHurtado\PaymentGateway\Omnipay\Netbank\Message\DisburseRequest::class,
            $request
        );
    }
}
