<?php

use Illuminate\Support\Str;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Tests\Models\AnotherUser;

beforeEach(function () {
    $this->gatewayMock = mock(PaymentGatewayInterface::class);
    app()->instance(PaymentGatewayInterface::class, $this->gatewayMock);
});

// Happy path: JSON request
it('returns a JSON response for disbursement requests via XHR', function () {
    $user = auth()->user();
    $user->wallet;

    $payload = [
        'reference' => 'REF-'.Str::random(5),
        'amount' => 100,
        'account_number' => '09171234567',
        'bank' => 'ALKBPHM2XXX',
        'via' => 'INSTAPAY',
    ];

    $expectedResponse = new DisburseResponseData(
        uuid: 'fake-uuid',
        transaction_id: 'TXN-1001',
        status: 'PENDING'
    );

    $this->gatewayMock
        ->shouldReceive('disburse')
        ->once()
        ->with($user, $payload)
        ->andReturn($expectedResponse);

    $response = $this->postJson(route('disburse-funds'), $payload);

    $response
        ->assertOk()
        ->assertJson([
            'event' => [
                'name' => 'disbursement.initiated',
                'data' => $expectedResponse->toArray(),
            ],
        ]);
})->skip();

// Happy path: Vue/Inertia request
it('redirects with event data when called from Vue (Inertia)', function () {
    $user = auth()->user();
    $user->wallet;

    $payload = [
        'reference' => 'REF-'.Str::random(5),
        'amount' => 100,
        'account_number' => '09171234567',
        'bank' => 'ALKBPHM2XXX',
        'via' => 'INSTAPAY',
    ];

    $expectedResponse = new DisburseResponseData(
        uuid: 'fake-uuid',
        transaction_id: 'TXN-1002',
        status: 'PENDING'
    );

    $this->gatewayMock
        ->shouldReceive('disburse')
        ->once()
        ->with($user, $payload)
        ->andReturn($expectedResponse);

    $response = $this->post(route('disburse-funds'), $payload, [
        'X-Inertia' => 'true',
    ]);

    $response
        ->assertStatus(302)
        ->assertSessionHas('event', [
            'name' => 'disbursement.initiated',
            'data' => $expectedResponse,
        ]);
})->skip();

// Happy path: Traditional redirect
it('redirects for standard form posts', function () {
    $user = auth()->user();
    $user->wallet;

    $payload = [
        'reference' => 'REF-'.Str::random(5),
        'amount' => 100,
        'account_number' => '09171234567',
        'bank' => 'ALKBPHM2XXX',
        'via' => 'INSTAPAY',
    ];

    $expectedResponse = new DisburseResponseData(
        uuid: 'fake-uuid',
        transaction_id: 'TXN-1003',
        status: 'PENDING'
    );

    $this->gatewayMock
        ->shouldReceive('disburse')
        ->once()
        ->with($user, $payload)
        ->andReturn($expectedResponse);

    $response = $this->post(route('disburse-funds'), $payload);

    $response->assertStatus(302);
})->skip();

// Failure: Invalid user (no wallet interface)
it('returns 403 if user does not support Wallet interface', function () {
    $nonWalletUser = AnotherUser::factory()->make();
    $this->actingAs($nonWalletUser);

    $response = $this->postJson(route('disburse-funds'), [
        'reference' => 'FAIL-REF',
        'amount' => 100,
        'account_number' => '09171234567',
        'bank' => 'ALKBPHM2XXX',
        'via' => 'INSTAPAY',
    ]);

    $response->assertStatus(403)->assertJsonFragment([
        'message' => 'User does not support wallet functionality',
    ]);
});

// Failure: Invalid input
it('fails validation with invalid disbursement data', function () {
    $response = $this->postJson(route('disburse-funds'), [
        'reference' => '',           // empty
        'amount' => 'invalid_amt',   // non-numeric
        'account_number' => '',      // empty
        'bank' => '',                // empty
        'via' => 'invalid_rail',     // not in config list
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reference', 'amount', 'account_number', 'bank', 'via']);
});
