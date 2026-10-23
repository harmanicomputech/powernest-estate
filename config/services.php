<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    'stron' => [
        'base_url' => env('STRON_BASE_URL'),
        'company' => env('STRON_COMPANY'),
        'username' => env('STRON_USERNAME'),
        'password' => env('STRON_PASSWORD'),
        'timeout' => (int) env('STRON_TIMEOUT', 45),
    ],

    'paystack' => [
        'enabled' => (bool) env('PAYSTACK_ENABLED', false),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => 'https://api.paystack.co',
    ],

    'payaza' => [
        'enabled' => (bool) env('PAYAZA_ENABLED', false),
        'public_key' => env('PAYAZA_PUBLIC_KEY'),
        'secret_key' => env('PAYAZA_SECRET_KEY'),
        'environment' => env('PAYAZA_ENVIRONMENT', 'test'),
        'verify_url' => env('PAYAZA_VERIFY_URL'),
        'webhook_secret' => env('PAYAZA_WEBHOOK_SECRET'),
        'checkout_script' => 'https://checkout-v2.payaza.africa/js/v1/bundle.js',
    ],
];
