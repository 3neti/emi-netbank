<?php

use Illuminate\Support\Facades\File;
use LBHurtado\PaymentGateway\Support\Address;

it('generates a valid address', function () {
    $address = Address::generate();

    expect(array_keys($address))->toMatchArray(['address1', 'city', 'country', 'postal_code'])
        ->and($address['address1'])->not->toBeEmpty()
        ->and($address['city'])->not->toBeEmpty()
        ->and($address['country'])->toBe('PH')
        ->and($address['postal_code'])->not->toBeEmpty();
});

it('keeps generated address lines within the provider limit', function (string|array $localities) {
    $postalCodes = [];
    for ($prefix = 10; $prefix <= 90; $prefix++) {
        $postalCodes[$prefix.'00'] = 'Test City';
        for ($suffix = 1; $suffix <= 5; $suffix++) {
            $postalCodes[$prefix.'0'.$suffix] = $localities;
        }
    }

    File::shouldReceive('get')
        ->with(documents_path('zip_codes_list.json'))
        ->andReturn(json_encode($postalCodes));

    $address = Address::generate();
    $original = is_array($localities) ? implode(' ', $localities) : $localities;

    expect($address['address1'])->not->toBeEmpty()
        ->and(mb_strlen($address['address1']))->toBeLessThanOrEqual(128)
        ->and(mb_check_encoding($address['address1'], 'UTF-8'))->toBeTrue()
        ->and(str_starts_with($original, $address['address1']))->toBeTrue()
        ->and($address['city'])->toBe('Test City')
        ->and($address['country'])->toBe('PH')
        ->and($address['postal_code'])->toMatch('/^[1-9][0-9]0[1-5]$/');

    if (mb_strlen($original) <= 128) {
        expect($address['address1'])->toBe($original);
    }
})->with([
    'postal code 1101' => [json_decode(file_get_contents(__DIR__.'/../../../resources/documents/zip_codes_list.json'), true)['1101']],
    'postal code 1105' => [json_decode(file_get_contents(__DIR__.'/../../../resources/documents/zip_codes_list.json'), true)['1105']],
    'exactly 128 characters' => [str_repeat('A', 128)],
    'short address' => ['Santa Cruz'],
    'multibyte address' => [str_repeat('ñ', 140)],
]);
