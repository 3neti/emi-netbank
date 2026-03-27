<?php

use Illuminate\Support\Facades\File;
use LBHurtado\PaymentGateway\Support\Address;

it('generates a valid address', function () {
    // Arrange: Mock the zip_codes_list.json file
    $json_file = 'zip_codes_list.json';
    $mocked_json_path = documents_path($json_file);

    $mocked_zip_codes = [
        '1000' => 'Metro Manila',
        '1001' => 'Santa Cruz',
        '1010' => 'Pandacan',
    ];

    // Simulate the file content
    File::shouldReceive('get')
        ->with($mocked_json_path)
        ->andReturn(json_encode($mocked_zip_codes));

    // Act: Generate a new address
    $address = Address::generate();

    // Assert: Validate the output
    expect($address)->toBeArray();
    expect(array_keys($address))->toMatchArray(['address1', 'city', 'country', 'postal_code']);

    // Assert: Check specific keys are not empty
    expect($address['address1'])->not->toBeEmpty();
    expect($address['city'])->not->toBeEmpty();
    expect($address['country'])->toBe('PH');
    expect($address['postal_code'])->not->toBeEmpty();

    //    // Assert: Check postal code exists in mocked data
    //    $postal_code_key = substr($address['postal_code'], 0, 2) . '00';
    //    dd($mocked_zip_codes, $postal_code_key);
    //    expect(isset($mocked_zip_codes[$postal_code_key]))
    //        ->toBeTrue();
});
