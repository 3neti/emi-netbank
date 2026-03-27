<?php

use Illuminate\Http\Response;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

beforeEach(function () {
    // Mock the gateway and bind it in the IoC container
    $this->gateway = Mockery::mock(PaymentGatewayInterface::class);
    $this->app->instance(PaymentGatewayInterface::class, $this->gateway);
});

it('confirms a disbursement using the controller', function () {
    // Define a sample payload
    $payload = [
        'operationId' => 'test-operation-id',
    ];

    // Mock `confirmDisbursement` on the gateway
    $this->gateway->shouldReceive('confirmDisbursement')
        ->once()
        ->with($payload['operationId'])
        ->andReturn(true); // Return success for this test

    // Act: Send a POST request to the route
    $response = $this->postJson(route('confirm-disbursement'), $payload);

    // Assert: Response status and content
    $response->assertStatus(Response::HTTP_OK);
    $response->assertSeeText('Disbursement confirmed!');
});

it('returns an error if the disbursement confirmation fails', function () {
    // Define a sample payload
    $payload = [
        'operationId' => 'test-operation-id',
    ];

    // Mock `confirmDisbursement` to simulate a failure
    $this->gateway->shouldReceive('confirmDisbursement')
        ->once()
        ->with($payload['operationId'])
        ->andReturn(false); // Simulate failure

    // Act: Send a POST request to the route
    $response = $this->postJson(route('confirm-disbursement'), $payload);

    // Assert: Response status and content
    $response->assertStatus(Response::HTTP_BAD_GATEWAY);
    $response->assertSeeText('Disbursement confirmation failed.');
});

it('validates the presence of operationId', function () {
    // Act: Send an invalid payload (missing operationId)
    $response = $this->postJson(route('confirm-disbursement'), []);

    // Assert: Status and validation error
    $response->assertStatus(\Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertSeeText('Disbursement confirmation failed.');
});
