<?php

return [
    'server' => [
        'end-point' => env('NETBANK_DISBURSEMENT_ENDPOINT'),
        'token-end-point' => env('NETBANK_TOKEN_ENDPOINT'),
        'qr-end-point' => env('NETBANK_QR_ENDPOINT'),
        'status-endpoint' => env('NETBANK_STATUS_ENDPOINT'),
    ],
    'client' => [
        'id' => env('NETBANK_CLIENT_ID', ''),
        'secret' => env('NETBANK_CLIENT_SECRET', ''),
        'alias' => env('NETBANK_CLIENT_ALIAS', ''),
    ],
    'source' => [
        'account_number' => env('NETBANK_SOURCE_ACCOUNT_NUMBER', ''),
        'sender' => [
            'customer_id' => env('NETBANK_SENDER_CUSTOMER_ID', ''),
        'address' => [
                'address1' => env('NETBANK_SENDER_ADDRESS_ADDRESS1', ''),
                'city' => env('NETBANK_SENDER_ADDRESS_CITY', ''),
                'country' => env('NETBANK_SENDER_ADDRESS_COUNTRY', 'PH'),
                'postal_code' => env('NETBANK_SENDER_ADDRESS_POSTAL_CODE', ''),
            ],
        ],
    ],
    'min' => env('MINIMUM_DISBURSEMENT', 1),
    'max' => env('MAXIMUM_DISBURSEMENT', 2),
    'variance' => [
        'min' => env('MINIMUM_VARIANCE', 0),
        'max' => env('MAXIMUM_VARIANCE', 0),
    ],
    'threshold_balance' => env('THRESHOLD_BALANCE', 2), // major
    'settlement_rails' => explode(',', env('SETTLEMENT_RAILS', 'INSTAPAY,PESONET')),
    'user' => [
        'system' => [
            'name' => env('SYSTEM_NAME', 'RLI DevOps'),
            'email' => env('SYSTEM_EMAIL', 'devops@joy-nostalg.com'),
            'mobile' => env('SYSTEM_MOBILE', '09178251991'),
            'password' => env('SYSTEM_PASSWORD', '#Password1'),
            'password_confirmation' => env('SYSTEM_PASSWORD', '#Password1'),
        ],
        'transaction_fee' => 15 * 100,
        'merchant_discount_rate' => 1.5 / 100,
        'tf' => 15 * 100,
        'mdr' => 1,
    ],
    'wallet' => [
        'initial_deposit' => env('INITIAL_DEPOSIT', 1000 * 1000 * 1000),
    ],
    'nova' => [
        'whitelist' => env('NOVA_WHITELIST', '*'),
    ],
    'merchant' => [
        'default' => [
            'city' => env('DEFAULT_MERCHANT_CITY', 'Manila'),
        ],
        'max_count' => env('MAX_MERCHANT_COUNT', 9),
    ],
    'bank' => [
        'default' => [
            'code' => env('DEFAULT_BANK_CODE', 'GXCHPHM2XXX'),
            'settlement_rail' => env('DEFAULT_SETTLEMENT_RAIL', 'INSTAPAY'),
        ],
    ],
];
