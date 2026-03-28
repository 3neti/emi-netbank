<?php

use LBHurtado\PaymentGateway\Data\ConfirmedDepositData;

it('parseDeposit returns ConfirmedDepositData without wallet', function () {
    $reflection = new ReflectionClass(ConfirmedDepositData::class);

    // ConfirmedDepositData should NOT have a wallet property
    expect($reflection->hasProperty('wallet'))->toBeFalse();
    expect($reflection->hasProperty('reference_code'))->toBeTrue();
    expect($reflection->hasProperty('amount'))->toBeTrue();
    expect($reflection->hasProperty('sender_name'))->toBeTrue();
});
