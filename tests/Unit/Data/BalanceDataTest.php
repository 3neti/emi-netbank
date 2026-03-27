<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Data;

use LBHurtado\PaymentGateway\Data\Wallet\BalanceData;
use LBHurtado\PaymentGateway\Tests\TestCase;

class BalanceDataTest extends TestCase
{
    public function test_creates_balance_data_with_minor_units()
    {
        $balance = new BalanceData(
            amount: 10000, // ₱100.00
            currency: 'PHP'
        );

        $this->assertEquals(10000, $balance->amount);
        $this->assertEquals('PHP', $balance->currency);
    }

    public function test_converts_to_major_units()
    {
        $balance = new BalanceData(
            amount: 15050, // ₱150.50
            currency: 'PHP'
        );

        $this->assertEquals(150.50, $balance->toMajor());
    }

    public function test_formats_balance_correctly()
    {
        $balance = new BalanceData(
            amount: 123456, // ₱1,234.56
            currency: 'PHP'
        );

        $this->assertEquals('1,234.56', $balance->formatted());
    }

    public function test_legacy_get_balance_method()
    {
        $balance = new BalanceData(
            amount: 50075, // ₱500.75
            currency: 'PHP'
        );

        $this->assertEquals(500.75, $balance->getBalance());
    }

    public function test_supports_optional_fields()
    {
        $balance = new BalanceData(
            amount: 10000,
            currency: 'PHP',
            account: '1234567890',
            retrieved_at: '2024-01-01 12:00:00',
            meta: ['source' => 'netbank']
        );

        $this->assertEquals('1234567890', $balance->account);
        $this->assertEquals('2024-01-01 12:00:00', $balance->retrieved_at);
        $this->assertEquals(['source' => 'netbank'], $balance->meta);
    }
}
