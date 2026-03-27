<?php

return [
    // HTTP client configuration
    'http' => [
        'timeout' => env('GATEWAY_HTTP_TIMEOUT', 15), // Request timeout in seconds (must be < PHP max_execution_time)
        'connect_timeout' => env('GATEWAY_HTTP_CONNECT_TIMEOUT', 5), // Connection timeout in seconds
    ],

    'gateways' => [
        'netbank' => [
            'class' => \LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway::class,
            'options' => [
                'clientId' => env('NETBANK_CLIENT_ID'),
                'clientSecret' => env('NETBANK_CLIENT_SECRET'),
                'tokenEndpoint' => env('NETBANK_TOKEN_ENDPOINT'),
                'apiEndpoint' => env('NETBANK_DISBURSEMENT_ENDPOINT'),
                'qrEndpoint' => env('NETBANK_QR_ENDPOINT'),
                'statusEndpoint' => env('NETBANK_STATUS_ENDPOINT'),
                'balanceEndpoint' => env('NETBANK_BALANCE_ENDPOINT'),
                'customerEndpoint' => env('NETBANK_CUSTOMER_ENDPOINT'),
                'accountEndpoint' => env('NETBANK_ACCOUNT_ENDPOINT'),
                'accountTypesEndpoint' => env('NETBANK_ACCOUNT_TYPES_ENDPOINT'),
                'testMode' => env('NETBANK_TEST_MODE', false),

                // Disbursement defaults
                'sourceAccountNumber' => env('NETBANK_SOURCE_ACCOUNT_NUMBER'),
                'senderCustomerId' => env('NETBANK_SENDER_CUSTOMER_ID'),
                'clientAlias' => env('NETBANK_CLIENT_ALIAS'),

                // Rail-specific configuration
                'rails' => [
                    'INSTAPAY' => [
                        'enabled' => env('NETBANK_INSTAPAY_ENABLED', true),
                        'min_amount' => 1, // ₱0.01 in centavos
                        'max_amount' => 50000 * 100, // ₱50,000 in centavos
                        'fee' => 1000, // ₱10 fee in centavos
                    ],
                    'PESONET' => [
                        'enabled' => env('NETBANK_PESONET_ENABLED', true),
                        'min_amount' => 1,
                        'max_amount' => 1000000 * 100, // ₱1M in centavos
                        'fee' => 2500, // ₱25 fee in centavos
                    ],
                ],
            ],
        ],
        'icash' => [
            'class' => \LBHurtado\PaymentGateway\Omnipay\ICash\Gateway::class,
            'options' => [
                'apiKey' => env('ICASH_API_KEY'),
                'apiSecret' => env('ICASH_API_SECRET'),
                'apiEndpoint' => env('ICASH_API_ENDPOINT'),
                'testMode' => env('ICASH_TEST_MODE', false),

                'rails' => [
                    'INSTAPAY' => [
                        'enabled' => env('ICASH_INSTAPAY_ENABLED', true),
                        'min_amount' => 1,
                        'max_amount' => 50000 * 100,
                    ],
                ],
            ],
        ],
    ],

    'default' => env('PAYMENT_GATEWAY', 'netbank'),

    // Feature flag for gradual rollout
    'use_omnipay' => env('USE_OMNIPAY', false),

    // KYC workaround settings
    'kyc' => [
        'randomize_address' => env('GATEWAY_RANDOMIZE_ADDRESS', true),
        'address_provider' => \LBHurtado\PaymentGateway\Support\Address::class,
    ],

    // Bank registry
    'banks' => [
        'json_path' => env('BANKS_JSON_PATH', 'banks.json'),
        'cache_enabled' => env('BANKS_CACHE_ENABLED', true),
        'cache_ttl' => env('BANKS_CACHE_TTL', 86400), // 24 hours
    ],
];
