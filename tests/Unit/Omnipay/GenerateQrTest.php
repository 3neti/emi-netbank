<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\GenerateQrRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\GenerateQrResponse;
use PHPUnit\Framework\TestCase;

class GenerateQrTest extends TestCase
{
    private Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway;
        $this->gateway->initialize([
            'apiUrl' => 'https://api.netbank.example.com',
            'clientId' => 'test-client-id',
            'clientSecret' => 'test-client-secret',
            'testMode' => true,
        ]);
    }

    public function test_creates_generate_qr_request()
    {
        $request = $this->gateway->generateQr([
            'accountNumber' => '1234567890',
            'reference' => 'QR-123',
        ]);

        $this->assertInstanceOf(GenerateQrRequest::class, $request);
        $this->assertEquals('1234567890', $request->getAccountNumber());
        $this->assertEquals('QR-123', $request->getReference());
    }

    public function test_creates_generate_qr_request_with_amount()
    {
        $request = $this->gateway->generateQr([
            'accountNumber' => '1234567890',
            'reference' => 'QR-123',
            'amount' => 50000,
            'currency' => 'PHP',
        ]);

        $this->assertEquals(50000, $request->getAmount());
        $this->assertEquals('PHP', $request->getCurrency());
    }

    public function test_generate_qr_data_without_amount()
    {
        $this->markTestSkipped();
        $request = $this->gateway->generateQr([
            'accountNumber' => '1234567890',
            'reference' => 'QR-123',
        ]);

        $data = $request->getData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('qr_code', $data);
        $this->assertEquals('1234567890', $data['qr_code']['account_number']);
        $this->assertEquals('QR-123', $data['qr_code']['reference']);
        $this->assertArrayNotHasKey('amount', $data['qr_code']);
    }

    public function test_generate_qr_data_with_amount()
    {
        $this->markTestSkipped();
        $request = $this->gateway->generateQr([
            'accountNumber' => '1234567890',
            'reference' => 'QR-123',
            'amount' => 50000,
            'currency' => 'PHP',
        ]);

        $data = $request->getData();

        $this->assertArrayHasKey('amount', $data['qr_code']);
        $this->assertEquals(50000, $data['qr_code']['amount']);
        $this->assertEquals('PHP', $data['qr_code']['currency']);
    }

    public function test_generate_qr_endpoint()
    {
        $this->markTestSkipped();
        $request = $this->gateway->generateQr([
            'accountNumber' => '1234567890',
            'reference' => 'QR-123',
        ]);

        // Verify apiUrl is passed from gateway to request
        $this->assertEquals('https://api.netbank.example.com', $request->getApiUrl());

        $this->assertEquals(
            'https://api.netbank.example.com/qr/generate',
            $request->getEndpoint()
        );
    }

    public function test_successful_generate_qr_response()
    {
        $this->markTestSkipped();
        $request = $this->gateway->generateQr([
            'accountNumber' => '1234567890',
            'reference' => 'QR-123',
        ]);

        $responseData = [
            'status' => 'success',
            'data' => [
                'qr_code' => '00020101021226370011com.netbank0109012345678...',
                'qr_url' => 'https://api.netbank.example.com/qr/view/abc123',
                'qr_id' => 'qr_abc123xyz',
                'expires_at' => '2024-12-31T23:59:59Z',
            ],
        ];

        $response = new GenerateQrResponse($request, $responseData);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('00020101021226370011com.netbank0109012345678...', $response->getQrCode());
        $this->assertEquals('https://api.netbank.example.com/qr/view/abc123', $response->getQrUrl());
        $this->assertEquals('qr_abc123xyz', $response->getQrId());
        $this->assertEquals('2024-12-31T23:59:59Z', $response->getExpiresAt());
        $this->assertNull($response->getMessage());
    }

    public function test_failed_generate_qr_response()
    {
        $request = $this->gateway->generateQr([
            'accountNumber' => '1234567890',
            'reference' => 'QR-123',
        ]);

        $responseData = [
            'status' => 'error',
            'message' => 'Invalid account number',
            'code' => 'INVALID_ACCOUNT',
        ];

        $response = new GenerateQrResponse($request, $responseData);

        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getQrCode());
        $this->assertEquals('Invalid account number', $response->getMessage());
        $this->assertEquals('INVALID_ACCOUNT', $response->getCode());
    }
}
