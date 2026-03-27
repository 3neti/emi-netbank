<?php

use Brick\Money\Money;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

// Shared mock for the payment gateway
beforeEach(function () {
    $this->gatewayMock = mock(PaymentGatewayInterface::class);
    app()->instance(PaymentGatewayInterface::class, $this->gatewayMock);
});

it('can generate a QR code', function () {
    // Arrange
    $account = '09171234568';
    $amount = 50;

    // ACt
    $this->gatewayMock
        ->shouldReceive('generate')
        ->withArgs(fn ($acct, $amt) => $acct === $account &&
            $amt instanceof \Brick\Money\Money &&
            $amt->isEqualTo(\Brick\Money\Money::of($amount, 'PHP'))
        )
        ->andReturn('some_image_bytes');

    $response = $this->postJson(route('generate-qrcode'), [
        'account' => $account,
        'amount' => $amount,
    ]);

    // Assert
    $response->assertOk();
    $response->assertJsonFragment([
        'event' => [
            'name' => 'qrcode.generated',
            'data' => 'some_image_bytes',
        ],
    ]);
});

// Test: Returns JSON response when requested
it('returns a JSON response for JSON requests', function () {
    // Arrange
    $account = '09171234567';
    $amount = 50;

    // Act
    $this->gatewayMock
        ->shouldReceive('generate')
        ->once()
        ->withArgs(fn ($acct, $amt) => $acct === $account &&
            $amt instanceof Money &&
            $amt->isEqualTo(Money::of($amount, 'PHP'))
        )
        ->andReturn('some_image_bytes');

    $response = $this->postJson(route('generate-qrcode'), [
        'account' => $account,
        'amount' => $amount,
    ]);

    // Assert
    $response
        ->assertStatus(200)
        ->assertJson([
            'event' => [
                'name' => 'qrcode.generated',
                'data' => 'some_image_bytes',
            ],
        ]);
});

// Test: Redirects with event data for Vue/Inertia requests
it('redirects with event data when called from Vue', function () {
    // Arrange
    $account = '09171234567';
    $amount = 50;

    // Act
    $this->gatewayMock
        ->shouldReceive('generate')
        ->once()
        ->withArgs(fn ($acct, $amt) => $acct === $account &&
            $amt instanceof Money &&
            $amt->isEqualTo(Money::of($amount, 'PHP'))
        )
        ->andReturn('some_image_bytes');

    $response = $this->post(route('generate-qrcode'), [
        'account' => $account,
        'amount' => $amount,
    ], [
        'X-Inertia' => 'true',
    ]);

    // Assert
    $response
        ->assertStatus(302)
        ->assertSessionHas('event', [
            'name' => 'qrcode.generated',
            'data' => 'some_image_bytes',
        ]);
});

// Test: Default redirect response for non-AJAX requests
it('redirects without event data for non-ajax requests', function () {
    // Arrange
    $account = '09171234567';
    $amount = 50;

    // Act
    $this->gatewayMock
        ->shouldReceive('generate')
        ->once()
        ->withArgs(fn ($acct, $amt) => $acct === $account &&
            $amt instanceof Money &&
            $amt->isEqualTo(Money::of($amount, 'PHP'))
        )
        ->andReturn('some_image_bytes');

    $response = $this->post(route('generate-qrcode'), [
        'account' => $account,
        'amount' => $amount,
    ]);

    // Assert
    $response->assertStatus(302);
});

// Test: Validation fails with invalid input
it('fails validation for invalid input', function () {
    // Act
    $response = $this->postJson(route('generate-qrcode'), [
        'account' => '', // Invalid: required field empty
        'amount' => 'invalid_amount', // Invalid: not numeric
    ]);

    // Assert
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account', 'amount']);
});
