<?php

use Brick\Money\Money;
use LBHurtado\PaymentGateway\Data\Netbank\Common\PayloadAmountData;

test('the num transformer works for non-zero num', function () {
    $data = PayloadAmountData::from([
        'cur' => 'PHP',
        'num' => Money::of(1000, 'PHP'),
    ]);

    expect($data->toArray()['cur'])->toBe('PHP');
    expect($data->toArray()['num'])->toBe('100000');
});

test('the num transformer works for zero num', function () {
    $data = PayloadAmountData::from([
        'cur' => 'PHP',
        'num' => Money::of(0, 'PHP'),
    ]);

    expect($data->toArray()['cur'])->toBe('PHP');
    expect($data->toArray()['num'])->toBe('');
});

test('the num cast works', function () {
    $data = PayloadAmountData::from([
        'cur' => 'PHP',
        'num' => 1000,
    ]);

    expect($data->toArray()['cur'])->toBe('PHP');
    expect($data->toArray()['num'])->toBe('100000');
});

test('the fromMoney works', function () {
    $money = Money::of(1000, 'PHP');
    $data = PayloadAmountData::from($money);

    expect($data->cur)->toBe('PHP');
    expect($data->num)->toBe($money);
});
