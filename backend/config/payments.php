<?php

return [
    /*
    | Which gateway handles a new payment. Sandbox is the default because the
    | real three all need merchant credentials that only exist once the business
    | is registered with them — see the drivers for what each one still needs.
    */
    'default' => env('PAYMENT_GATEWAY', 'sandbox'),

    // Platform's cut of an agency booking. Stored per payment when charged, so
    // changing this never rewrites history.
    'commission_rate' => (float) env('PLATFORM_COMMISSION_RATE', 0.05),

    // Agency subscription pricing, in whole rupees. Yearly is ten months —
    // "two months free", as the business plan puts it.
    'plans' => [
        'pro'     => ['monthly' => 2999, 'yearly' => 29990],
        'premium' => ['monthly' => 7999, 'yearly' => 79990],
    ],

    'gateways' => [
        'sandbox'   => [],
        'jazzcash'  => [
            'merchant_id'   => env('JAZZCASH_MERCHANT_ID'),
            'password'      => env('JAZZCASH_PASSWORD'),
            'integrity_salt' => env('JAZZCASH_INTEGRITY_SALT'),
            'endpoint'      => env('JAZZCASH_ENDPOINT', 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/2.0/Purchase/DoMWalletTransaction'),
        ],
        'easypaisa' => [
            'store_id'      => env('EASYPAISA_STORE_ID'),
            'account_num'   => env('EASYPAISA_ACCOUNT_NUM'),
            'hash_key'      => env('EASYPAISA_HASH_KEY'),
            'endpoint'      => env('EASYPAISA_ENDPOINT', 'https://easypaisa.com.pk/easypay/Index.jsf'),
        ],
        'stripe'    => [
            'secret'        => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],
];
