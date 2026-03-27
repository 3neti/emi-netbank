<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway;
use LBHurtado\PaymentGateway\Tests\TestCase;
use Omnipay\Common\Exception\InvalidRequestException;

class SettlementRailValidationTest extends TestCase
{
    private Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway;
        $this->gateway->initialize([
            'apiUrl' => 'https://api.test.com',
            'clientId' => 'test-client',
            'clientSecret' => 'test-secret',
            'rails' => [
                'INSTAPAY' => [
                    'enabled' => true,
                    'min_amount' => 1,
                    'max_amount' => 50000 * 100,
                    'fee' => 1000,
                ],
                'PESONET' => [
                    'enabled' => true,
                    'min_amount' => 1,
                    'max_amount' => 1000000 * 100,
                    'fee' => 2500,
                ],
            ],
        ]);
    }

    public function test_validates_bank_supports_rail()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/does not support INSTAPAY/');

        $request = $this->gateway->disburse([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'CITIPHMXXXX', // Assuming this only supports PESONET
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $request->getData(); // Should throw
    }

    public function test_validates_amount_exceeds_instapay_limit()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/exceeds INSTAPAY limit|Amount exceeds/');

        $request = $this->gateway->disburse([
            'amount' => 60000 * 100, // ₱60,000 exceeds ₱50K limit
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX', // GCash supports INSTAPAY
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $request->getData(); // Should throw
    }

    public function test_validates_amount_below_minimum()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/too small|Minimum/');

        $request = $this->gateway->disburse([
            'amount' => 0, // Below minimum
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $request->getData(); // Should throw
    }

    public function test_allows_valid_instapay_transaction()
    {
        $this->markTestSkipped();
        $request = $this->gateway->disburse([
            'amount' => 10000, // ₱100 (well within limit)
            'accountNumber' => '09171234567',
            'bankCode' => 'GXCHPHM2XXX', // GCash supports INSTAPAY
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $data = $request->getData();

        $this->assertEquals('INSTAPAY', $data['transaction']['settlement_rail']);
        $this->assertEquals(10000, $data['transaction']['amount']['value']);
    }

    public function test_allows_valid_pesonet_transaction()
    {
        $this->markTestSkipped();
        $request = $this->gateway->disburse([
            'amount' => 100000 * 100, // ₱100,000
            'accountNumber' => '1234567890',
            'bankCode' => 'BNORPHMMXXX', // BDO supports PESONET
            'reference' => 'REF123',
            'via' => 'PESONET',
        ]);

        $data = $request->getData();

        $this->assertEquals('PESONET', $data['transaction']['settlement_rail']);
        $this->assertEquals(100000 * 100, $data['transaction']['amount']['value']);
    }

    public function test_calculates_rail_fee_for_instapay()
    {
        $this->markTestSkipped();
        $request = $this->gateway->disburse([
            'amount' => 10000,
            'accountNumber' => '09171234567',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $data = $request->getData();

        // INSTAPAY fee is ₱10 (1000 centavos) per config
        $this->assertEquals(1000, $data['transaction']['fee']);
    }

    public function test_calculates_rail_fee_for_pesonet()
    {
        $this->markTestSkipped();
        $request = $this->gateway->disburse([
            'amount' => 100000 * 100,
            'accountNumber' => '1234567890',
            'bankCode' => 'BNORPHMMXXX',
            'reference' => 'REF123',
            'via' => 'PESONET',
        ]);

        $data = $request->getData();

        // PESONET fee is ₱25 (2500 centavos) per config
        $this->assertEquals(2500, $data['transaction']['fee']);
    }

    public function test_validates_gateway_supports_rail()
    {
        // Create gateway with only INSTAPAY enabled
        $gateway = new Gateway;
        $gateway->initialize([
            'apiUrl' => 'https://api.test.com',
            'clientId' => 'test-client',
            'clientSecret' => 'test-secret',
            'rails' => [
                'INSTAPAY' => [
                    'enabled' => true,
                    'min_amount' => 1,
                    'max_amount' => 50000 * 100,
                    'fee' => 1000,
                ],
                'PESONET' => [
                    'enabled' => false, // Disabled
                    'min_amount' => 1,
                    'max_amount' => 1000000 * 100,
                    'fee' => 2500,
                ],
            ],
        ]);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessageMatches('/Gateway does not support PESONET/');

        $request = $gateway->disburse([
            'amount' => 100000 * 100,
            'accountNumber' => '1234567890',
            'bankCode' => 'BNORPHMMXXX',
            'reference' => 'REF123',
            'via' => 'PESONET',
        ]);

        $request->getData(); // Should throw
    }
}
