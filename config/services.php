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
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'billing' => [
        'base_url' => env('BILLING_SERVER_URL', 'https://billing-server.test'),
        'api_token' => env('BILLING_SERVER_API_TOKEN'),
        'amount_lookup_endpoint' => env('BILLING_SERVER_AMOUNT_LOOKUP_ENDPOINT', '/bill-details'),
        'extend_plan_endpoint' => env('BILLING_SERVER_EXTEND_PLAN_ENDPOINT', '/extend-plan'),
        'payment_status_endpoint' => env('BILLING_SERVER_PAYMENT_STATUS_ENDPOINT', '/payment-status'),
        'processing_grace_seconds' => (int) env('BILLING_PROCESSING_GRACE_SECONDS', 120),
        'processing_retry_seconds' => (int) env('BILLING_PROCESSING_RETRY_SECONDS', 300),
        'processing_max_age_seconds' => (int) env('BILLING_PROCESSING_MAX_AGE_SECONDS', 3600),
    ],

    'broadband' => [
        'url' => env('BROADBAND_API_URL'),
    ],
];
