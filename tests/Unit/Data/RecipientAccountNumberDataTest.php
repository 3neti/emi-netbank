<?php

use Illuminate\Support\Facades\Config;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;

it('extracts alias and reference code when alias prefixes the account number', function () {
    Config::set('disbursement.client.alias', '91500');

    $input = '9150009173011987';
    $dto = RecipientAccountNumberData::fromRecipientAccountNumber($input);

    expect($dto->alias)
        ->toBe('91500');

    expect($dto->referenceCode)
        ->toBe('09173011987');
});

it('throws if the configured alias is empty', function () {
    Config::set('disbursement.client.alias', '');

    RecipientAccountNumberData::fromRecipientAccountNumber('9150009173011987');
})->throws(
    InvalidArgumentException::class,
    "Configuration key 'disbursement.client.alias' is not set or empty.",
);

it('throws if the account number does not start with the alias', function () {
    Config::set('disbursement.client.alias', '12345');

    RecipientAccountNumberData::fromRecipientAccountNumber('9150009173011987');
})->throws(
    InvalidArgumentException::class,
    "Recipient account number must start with the alias '12345'. Given: 9150009173011987",
);
