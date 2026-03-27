<?php

return [
    'models' => [
        'user' => class_exists(App\Models\User::class)
            ? App\Models\User::class
            : LBHurtado\PaymentGateway\Tests\Models\User::class,
    ],
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
    |
    | Configure Direct Checkout (Collection) endpoints and credentials.
    | This allows users to pay via redirect to their bank/e-wallet apps.
    |
    */
    'netbank' => [
        'direct_checkout' => [
            'use_fake' => env('NETBANK_DIRECT_CHECKOUT_USE_FAKE', false),
            'access_key' => env('NETBANK_DIRECT_CHECKOUT_ACCESS_KEY'),
            'secret_key' => env('NETBANK_DIRECT_CHECKOUT_SECRET_KEY'),
            'endpoint' => env('NETBANK_DIRECT_CHECKOUT_ENDPOINT', 'https://api.netbank.ph/v1/collect/checkout'),
            'transaction_endpoint' => env('NETBANK_DIRECT_CHECKOUT_TRANSACTION_ENDPOINT', 'https://api.netbank.ph/v1/collect/transactions'),
            'institutions_endpoint' => env('NETBANK_DIRECT_CHECKOUT_INSTITUTIONS_ENDPOINT', 'https://api.netbank.ph/v1/collect/financial_institutions'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Top-Up Configuration
    |--------------------------------------------------------------------------
    |
    | Configure limits and behavior for wallet top-ups.
    |
    */
    'top_up' => [
        'min_amount' => env('TOP_UP_MIN_AMOUNT', 1),
        'max_amount' => env('TOP_UP_MAX_AMOUNT', 50000),
        'reference_prefix' => env('TOP_UP_REFERENCE_PREFIX', 'TOPUP'),
        'auto_confirm_fake' => env('TOP_UP_AUTO_CONFIRM_FAKE', false),
    ],

    'gateway' => LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway::class,

    /*
    |--------------------------------------------------------------------------
    | System Account
    |--------------------------------------------------------------------------
    |
    | The system's main account for receiving payments (top-ups, voucher payments, etc.).
    | Format: mobile number with area code (e.g., 09173011987)
    |
    */
    'system_account' => env('SYSTEM_ACCOUNT', env('APP_MOBILE')),

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
