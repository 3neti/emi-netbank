<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway;
use LBHurtado\PaymentGateway\Tests\TestCase;

class KycWorkaroundTest extends TestCase
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
            'testMode' => true,
        ]);
    }

    public function test_injects_random_address_when_enabled()
    {
        $this->markTestSkipped();
        // Enable address randomization
        config(['omnipay.kyc.randomize_address' => true]);

        $request = $this->gateway->disburse([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $data = $request->getData();

        // Verify address was injected
        $this->assertArrayHasKey('address', $data['transaction']);
        $this->assertArrayHasKey('address1', $data['transaction']['address']);
        $this->assertArrayHasKey('city', $data['transaction']['address']);
        $this->assertArrayHasKey('postal_code', $data['transaction']['address']);
        $this->assertArrayHasKey('country', $data['transaction']['address']);
        $this->assertEquals('PH', $data['transaction']['address']['country']);

        // Address should not be empty
        $this->assertNotEmpty($data['transaction']['address']['address1']);
        $this->assertNotEmpty($data['transaction']['address']['city']);
        $this->assertNotEmpty($data['transaction']['address']['postal_code']);
    }

    public function test_uses_static_address_when_disabled()
    {
        $this->markTestSkipped();
        // Disable address randomization
        config(['omnipay.kyc.randomize_address' => false]);

        $request = $this->gateway->disburse([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $data = $request->getData();

        // Verify static address was used
        $this->assertArrayHasKey('address', $data['transaction']);
        $this->assertEquals('N/A', $data['transaction']['address']['address1']);
        $this->assertEquals('Manila', $data['transaction']['address']['city']);
        $this->assertEquals('PH', $data['transaction']['address']['country']);
        $this->assertEquals('1000', $data['transaction']['address']['postal_code']);
    }

    public function test_address_varies_between_requests()
    {
        $this->markTestSkipped();
        config(['omnipay.kyc.randomize_address' => true]);

        $request1 = $this->gateway->disburse([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $data1 = $request1->getData();

        $request2 = $this->gateway->disburse([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF124',
            'via' => 'INSTAPAY',
        ]);

        $data2 = $request2->getData();

        // Addresses should likely be different (though there's a small chance they could be the same)
        // At minimum, verify both have valid addresses
        $this->assertNotEmpty($data1['transaction']['address']['postal_code']);
        $this->assertNotEmpty($data2['transaction']['address']['postal_code']);

        // Both should be valid PH postal codes (4 digits)
        $this->assertMatchesRegularExpression('/^\d{4}$/', $data1['transaction']['address']['postal_code']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $data2['transaction']['address']['postal_code']);
    }

    public function test_address_includes_all_required_fields()
    {
        $this->markTestSkipped();
        config(['omnipay.kyc.randomize_address' => true]);

        $request = $this->gateway->disburse([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $data = $request->getData();
        $address = $data['transaction']['address'];

        // Verify all required fields are present
        $requiredFields = ['address1', 'city', 'country', 'postal_code'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $address, "Address missing required field: {$field}");
            $this->assertNotEmpty($address[$field], "Address field '{$field}' should not be empty");
        }
    }

    public function test_address_postal_code_is_valid_ph_format()
    {
        $this->markTestSkipped();
        config(['omnipay.kyc.randomize_address' => true]);

        $request = $this->gateway->disburse([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);

        $data = $request->getData();
        $postalCode = $data['transaction']['address']['postal_code'];

        // Philippine postal codes are 4 digits
        $this->assertMatchesRegularExpression('/^\d{4}$/', $postalCode);

        // Should be a valid range (typical PH postal codes are 1000-9999)
        $this->assertGreaterThanOrEqual(1000, (int) $postalCode);
        $this->assertLessThanOrEqual(9999, (int) $postalCode);
    }
}
