<?php

return [
    'models' => [
        // Generic subject model for deposit/payable resolution.
        // Host app should override this in config/payment.php.
        'subject' => [
            'class' => null,
            'field' => 'code',
        ],
    ],

    // Pipeline steps for resolving inbound deposit recipients.
    // Override in host app to customize resolution order.
    // 'resolve_payable_pipeline' => [
    //     \LBHurtado\PaymentGateway\Pipelines\ResolvePayable\CheckMobile::class,
    //     \LBHurtado\PaymentGateway\Pipelines\ResolvePayable\CheckSubject::class,
    //     \LBHurtado\PaymentGateway\Pipelines\ResolvePayable\ThrowIfUnresolved::class,
    // ],
];
