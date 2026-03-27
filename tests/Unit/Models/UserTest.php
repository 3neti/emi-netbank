<?php

use LBHurtado\PaymentGateway\Tests\Models\User;

it('has fillable properties', function () {
    $moneyIssuer = User::factory()->make();

    expect($moneyIssuer->getFillable())->toBe(['name', 'email', 'password', 'mobile']);
});

it('has a factory', function () {
    $user = User::factory()->create();
    expect($user)->toBeInstanceOf(User::class);
});

it('is authenticable', function () {
    $user = auth()->user();
    expect($user)->toBeInstanceOf(User::class);
});

it('can create a user', function () {
    $data = [
        'name' => 'John Doe',
        'email' => 'john@doe.com',
        'password' => 'password',
    ];

    $user = User::create($data);

    // Assert the record was saved in the database
    expect(User::find($user->id))->not->toBeNull();
    $this->assertDatabaseHas('users', $data); // Laravel assertion helper

    // Verify attributes
    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@doe.com');
});

it('can return a factory instance', function () {
    $factory = User::newFactory();

    expect($factory)->toBeInstanceOf(\LBHurtado\PaymentGateway\Database\Factories\UserFactory::class);
});
