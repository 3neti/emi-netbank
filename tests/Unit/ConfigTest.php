<?php

it('can read the disbursement config file', function () {
    // Server Configuration
    expect(config('disbursement.server.end-point'))->toBe(env('NETBANK_DISBURSEMENT_ENDPOINT'));
    expect(config('disbursement.server.token-end-point'))->toBe(env('NETBANK_TOKEN_ENDPOINT'));
    expect(config('disbursement.server.qr-end-point'))->toBe(env('NETBANK_QR_ENDPOINT'));
//    expect(config('disbursement.server.status-end-point'))->toBe(env('NETBANK_STATUS_ENDPOINT'));

    // Client Configuration
    expect(config('disbursement.client.id'))->toBe(env('NETBANK_CLIENT_ID', ''));
    expect(config('disbursement.client.secret'))->toBe(env('NETBANK_CLIENT_SECRET', ''));
    expect(config('disbursement.client.alias'))->toBe(env('NETBANK_CLIENT_ALIAS', ''));

    // Source Configuration
    expect(config('disbursement.source.account_number'))->toBe(env('NETBANK_SOURCE_ACCOUNT_NUMBER', ''));
    expect(config('disbursement.source.sender.customer_id'))->toBe(env('NETBANK_SENDER_CUSTOMER_ID', ''));
    expect(config('disbursement.source.sender.address.address1'))->toBe(env('NETBANK_SENDER_ADDRESS_ADDRESS1', ''));
    expect(config('disbursement.source.sender.address.city'))->toBe(env('NETBANK_SENDER_ADDRESS_CITY', ''));
    expect(config('disbursement.source.sender.address.country'))->toBe(env('NETBANK_SENDER_ADDRESS_COUNTRY', 'PH'));
    expect(config('disbursement.source.sender.address.postal_code'))->toBe(env('NETBANK_SENDER_ADDRESS_POSTAL_CODE', ''));

    // Disbursement Limits and Variance
    expect(config('disbursement.min'))->toBe(env('MINIMUM_DISBURSEMENT', 1));
    expect(config('disbursement.max'))->toBe(env('MAXIMUM_DISBURSEMENT', 2));
    expect(config('disbursement.variance.min'))->toBe(env('MINIMUM_VARIANCE', 0));
    expect(config('disbursement.variance.max'))->toBe(env('MAXIMUM_VARIANCE', 0));

    // Settlement Rails
    expect(config('disbursement.settlement_rails'))->toBe(
        explode(',', env('SETTLEMENT_RAILS', 'INSTAPAY,PESONET'))
    );

    // User System Information
    expect(config('disbursement.user.system.name'))->toBe(env('SYSTEM_NAME', 'RLI DevOps'));
    expect(config('disbursement.user.system.email'))->toBe(env('SYSTEM_EMAIL', 'devops@joy-nostalg.com'));
    expect(config('disbursement.user.system.mobile'))->toBe(env('SYSTEM_MOBILE', '09178251991'));
    expect(config('disbursement.user.system.password'))->toBe(env('SYSTEM_PASSWORD', '#Password1'));
    expect(config('disbursement.user.system.password_confirmation'))->toBe(env('SYSTEM_PASSWORD', '#Password1'));

    // User Fees and Discounts
    expect(config('disbursement.user.transaction_fee'))->toBe(15 * 100); // Static Value
    expect(config('disbursement.user.merchant_discount_rate'))->toBe(1.5 / 100); // Static Value
    expect(config('disbursement.user.tf'))->toBe(15 * 100); // Static Value
    expect(config('disbursement.user.mdr'))->toBe(1); // Static Value

    // Wallet Configuration
    expect(config('disbursement.wallet.initial_deposit'))->toBe(env('INITIAL_DEPOSIT', 1000 * 1000 * 1000));

    // Nova Configuration
    expect(config('disbursement.nova.whitelist'))->toBe(env('NOVA_WHITELIST', '*'));

    // Merchant Configuration
    expect(config('disbursement.merchant.default.city'))->toBe(env('DEFAULT_MERCHANT_CITY', 'Manila'));
    expect(config('disbursement.merchant.max_count'))->toBe(env('MAX_MERCHANT_COUNT', 9));

    // Bank Configuration
    expect(config('disbursement.bank.default.code'))->toBe(env('DEFAULT_BANK_CODE', 'GXCHPHM2XXX'));
    expect(config('disbursement.bank.default.settlement_rail'))->toBe(env('DEFAULT_SETTLEMENT_RAIL', 'INSTAPAY'));
});
