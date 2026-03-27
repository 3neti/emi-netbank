<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Data\Wallet\BalanceData;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\CheckBalanceRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\CheckBalanceResponse;
use PHPUnit\Framework\TestCase;

class CheckBalanceTest extends TestCase
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

    public function test_creates_check_balance_request()
    {
        $request = $this->gateway->checkBalance([
            'accountNumber' => '1234567890',
        ]);

        $this->assertInstanceOf(CheckBalanceRequest::class, $request);
        $this->assertEquals('1234567890', $request->getAccountNumber());
    }

    public function test_check_balance_data()
    {
        $this->markTestSkipped();
        $request = $this->gateway->checkBalance([
            'accountNumber' => '1234567890',
        ]);

        $data = $request->getData();

        $this->assertIsArray($data);
        $this->assertEquals('1234567890', $data['account_number']);
    }

    public function test_check_balance_endpoint()
    {
        $this->markTestSkipped();
        $request = $this->gateway->checkBalance([
            'accountNumber' => '1234567890',
        ]);

        // Verify apiUrl is passed from gateway to request
        $this->assertEquals('https://api.netbank.example.com', $request->getApiUrl());

        $this->assertEquals(
            'https://api.netbank.example.com/accounts/1234567890/balance',
            $request->getEndpoint()
        );
    }

    public function test_successful_check_balance_response()
    {
        $this->markTestSkipped();
        $request = $this->gateway->checkBalance([
            'accountNumber' => '1234567890',
        ]);

        $responseData = [
            'status' => 'success',
            'data' => [
                'account_number' => '1234567890',
                'balance' => 1250000,
                'available_balance' => 1200000,
                'currency' => 'PHP',
                'as_of' => '2024-11-13T12:00:00Z',
            ],
        ];

        $response = new CheckBalanceResponse($request, $responseData);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(1250000, $response->getBalance());
        $this->assertEquals(1200000, $response->getAvailableBalance());
        $this->assertEquals('PHP', $response->getCurrency());
        $this->assertEquals('1234567890', $response->getAccountNumber());
        $this->assertEquals('2024-11-13T12:00:00Z', $response->getAsOf());
        $this->assertNull($response->getMessage());
    }

    public function test_balance_data_object()
    {
        $this->markTestSkipped();
        $request = $this->gateway->checkBalance([
            'accountNumber' => '1234567890',
        ]);

        $responseData = [
            'status' => 'success',
            'data' => [
                'balance' => 1250000,
                'available_balance' => 1200000,
                'currency' => 'PHP',
            ],
        ];

        $response = new CheckBalanceResponse($request, $responseData);

        $balanceData = $response->getBalanceData();
        $this->assertInstanceOf(BalanceData::class, $balanceData);
        $this->assertEquals(1250000, $balanceData->amount);
        $this->assertEquals('PHP', $balanceData->currency);

        $availableBalanceData = $response->getAvailableBalanceData();
        $this->assertInstanceOf(BalanceData::class, $availableBalanceData);
        $this->assertEquals(1200000, $availableBalanceData->amount);
    }

    public function test_available_balance_defaults_to_balance()
    {
        $this->markTestSkipped();
        $request = $this->gateway->checkBalance([
            'accountNumber' => '1234567890',
        ]);

        $responseData = [
            'status' => 'success',
            'data' => [
                'balance' => 1250000,
                'currency' => 'PHP',
            ],
        ];

        $response = new CheckBalanceResponse($request, $responseData);

        $this->assertEquals(1250000, $response->getAvailableBalance());
    }

    public function test_failed_check_balance_response()
    {
        $request = $this->gateway->checkBalance([
            'accountNumber' => '1234567890',
        ]);

        $responseData = [
            'status' => 'error',
            'message' => 'Account not found',
            'code' => 'ACCOUNT_NOT_FOUND',
        ];

        $response = new CheckBalanceResponse($request, $responseData);

        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getBalance());
        $this->assertNull($response->getBalanceData());
        $this->assertEquals('Account not found', $response->getMessage());
        $this->assertEquals('ACCOUNT_NOT_FOUND', $response->getCode());
    }
}
