<?php

namespace LBHurtado\PaymentGateway\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Address
{
    public static function generate(): array
    {
        $json_file = 'zip_codes_list.json';
        $json_path = documents_path($json_file);
        $array = json_decode(File::get($json_path), true);
        $first_two = random_int(10, 90);
        $last_two = '0'.random_int(1, 5);
        $postal_code = $first_two.$last_two;

        $address = null;
        try {
            $city = $array[$first_two.'00'];
            $city = is_array($city) ? implode(' ', $city) : $city;
            $address1 = $array[$postal_code];
            $address1 = is_array($address1) ? implode(' ', $address1) : $address1;
            $address1 = Str::limit($address1, 128, '');
            $country = 'PH';
            $address = compact('address1', 'city', 'country', 'postal_code');
        } catch (\ErrorException $exception) {

        }

        return $address ? $address : static::generate();
    }
}
