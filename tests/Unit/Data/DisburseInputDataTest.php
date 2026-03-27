<?php

use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;

test('sanitizes account number by removing non-numeric characters', function (string $input, string $expected) {
    $data = new DisburseInputData(
        reference: 'TEST-REF',
        amount: 100,
        account_number: $input,
        bank: 'GXCHPHM2XXX',
        via: 'INSTAPAY'
    );

    expect($data->account_number)->toBe($expected);
})->with([
    'spaces' => ['0 917 301 1987', '09173011987'],
    'dashes' => ['0917-301-1987', '09173011987'],
    'plus_sign' => ['+639173011987', '639173011987'],
    'mixed_formatting' => ['0917-301 1987', '09173011987'],
    'already_clean' => ['09173011987', '09173011987'],
    'dots' => ['0917.301.1987', '09173011987'],
    'parentheses' => ['(0917) 301-1987', '09173011987'],
]);

test('preserves numeric only account numbers', function () {
    $data = new DisburseInputData(
        reference: 'TEST-REF',
        amount: 100,
        account_number: '09173011987',
        bank: 'GXCHPHM2XXX',
        via: 'INSTAPAY'
    );

    expect($data->account_number)->toBe('09173011987');
});
