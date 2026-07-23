<?php

use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;

return [
    'default' => env('PAYMENT_GATEWAY', 'netbank'),

    'drivers' => [
        'netbank' => [
            'base_url' => env('NETBANK_API_URL'),
            'api_key' => env('NETBANK_API_KEY'),
        ],
        'icash' => [
            'base_url' => env('ICASH_API_URL'),
            'api_key' => env('ICASH_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | NetBank Direct Checkout Configuration
    |--------------------------------------------------------------------------
    */
    'netbank' => [
        'funding' => [
            'api_url' => env('NETBANK_FUNDING_API_URL', 'https://api.netbank.ph'),
            'token_url' => env('NETBANK_FUNDING_TOKEN_URL', 'https://auth.netbank.ph/oauth2/token'),
            'client_id' => env('NETBANK_FUNDING_CLIENT_ID', env('NETBANK_CLIENT_ID')),
            'client_secret' => env('NETBANK_FUNDING_CLIENT_SECRET', env('NETBANK_CLIENT_SECRET')),
            'corporate_account_number' => env('NETBANK_FUNDING_CORPORATE_ACCOUNT_NUMBER', env('NETBANK_SOURCE_ACCOUNT_NUMBER')),
            'corporate_account_name' => env('NETBANK_FUNDING_CORPORATE_ACCOUNT_NAME', env('NETBANK_SOURCE_ACCOUNT_NAME')),
            'vca_alias' => env('NETBANK_FUNDING_VCA_ALIAS', env('NETBANK_CLIENT_ALIAS')),
            'vca_alias_token' => env('NETBANK_FUNDING_VCA_ALIAS_TOKEN'),
            'reference_key' => env('NETBANK_FUNDING_REFERENCE_KEY', env('APP_KEY')),
            'qr_endpoint' => env('NETBANK_FUNDING_QR_ENDPOINT', env('NETBANK_QR_ENDPOINT')),
            'qr_merchant_name' => env('NETBANK_FUNDING_QR_MERCHANT_NAME'),
            'qr_merchant_city' => env('NETBANK_FUNDING_QR_MERCHANT_CITY'),
            'qr_purpose' => env('NETBANK_FUNDING_QR_PURPOSE', 'Account funding'),
            'qr_resolution' => env('NETBANK_FUNDING_QR_RESOLUTION', 480),
            'pre_transaction_validation_enabled' => env('NETBANK_FUNDING_PRE_TRANSACTION_VALIDATION_ENABLED', true),
            'exact_limits_enabled' => env('NETBANK_FUNDING_EXACT_LIMITS_ENABLED', true),
            'connect_timeout_seconds' => env('NETBANK_FUNDING_CONNECT_TIMEOUT_SECONDS', 5),
            'timeout_seconds' => env('NETBANK_FUNDING_TIMEOUT_SECONDS', 15),
            'verification_lookback_days' => env('NETBANK_FUNDING_VERIFICATION_LOOKBACK_DAYS', 7),
            'webhook' => [
                'allowed_ips' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('NETBANK_FUNDING_WEBHOOK_ALLOWED_IPS', '')),
                ))),
                'expected_content_type' => 'text/plain;charset=ISO-8859-1',
            ],
        ],
        'direct_checkout' => [
            'use_fake' => env('NETBANK_DIRECT_CHECKOUT_USE_FAKE', false),
            'access_key' => env('NETBANK_DIRECT_CHECKOUT_ACCESS_KEY'),
            'secret_key' => env('NETBANK_DIRECT_CHECKOUT_SECRET_KEY'),
            'endpoint' => env('NETBANK_DIRECT_CHECKOUT_ENDPOINT', 'https://api.netbank.ph/v1/collect/checkout'),
            'transaction_endpoint' => env('NETBANK_DIRECT_CHECKOUT_TRANSACTION_ENDPOINT', 'https://api.netbank.ph/v1/collect/transactions'),
            'institutions_endpoint' => env('NETBANK_DIRECT_CHECKOUT_INSTITUTIONS_ENDPOINT', 'https://api.netbank.ph/v1/collect/financial_institutions'),
        ],
    ],

    'gateway' => NetbankPaymentGateway::class,

    /*
    |--------------------------------------------------------------------------
    | QR Code Caching
    |--------------------------------------------------------------------------
    |
    | Configure how long QR codes should be cached (in seconds).
    | Set to 0 to disable caching. Default is 1 hour (3600 seconds).
    | This reduces API calls to the payment gateway for QR generation.
    |
    */
    'qr_cache_ttl' => env('PAYMENT_GATEWAY_QR_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | QR Merchant Name Template
    |--------------------------------------------------------------------------
    |
    | Configure how the merchant name appears in QR codes.
    | Available variables: {name}, {city}, {app_name}
    |
    | Examples:
    |   "{name} • {city}"     => "3neti R&D OPC • Manila"
    |   "{name}"              => "3neti R&D OPC"
    |   "{app_name}"          => "Redeem-X"
    |   "{city} - {name}"     => "Manila - 3neti R&D OPC"
    |
    */
    'qr_merchant_name' => [
        'template' => env('QR_MERCHANT_NAME_TEMPLATE', '{name} - {city}'),
        'uppercase' => env('QR_MERCHANT_NAME_UPPERCASE', false),
        'fallback' => env('QR_MERCHANT_NAME_FALLBACK', config('app.name')),
    ],

    'routes' => [
        'enabled' => env('PAYMENT_GATEWAY_ROUTES_ENABLED', true),

        'prefix' => env('PAYMENT_GATEWAY_ROUTE_PREFIX', 'api'),

        'middleware' => ['api'],

        'name_prefix' => env('PAYMENT_GATEWAY_ROUTE_NAME_PREFIX'), // e.g., 'pg.'

        'version' => env('PAYMENT_GATEWAY_ROUTE_VERSION'), // e.g., 'v1'

        'domain' => env('PAYMENT_GATEWAY_DOMAIN'), // optional
    ],
];
