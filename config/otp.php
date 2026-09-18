<?php

return [
    'provider' => env('OTP_PROVIDER', 'mock'),
    'cache_store' => env('OTP_CACHE_STORE', 'redis'),

    'challenge_ttl' => (int) env('OTP_CHALLENGE_TTL', 300),
    'verification_token_ttl' => (int) env('OTP_VERIFICATION_TOKEN_TTL', 600),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_cooldown' => (int) env('OTP_RESEND_COOLDOWN', 60),

    'rate_limits' => [
        'otp_request' => [
            'max_attempts' => (int) env('OTP_REQUEST_RATE_LIMIT', 3),
            'decay_seconds' => (int) env('OTP_REQUEST_RATE_DECAY', 3600),
        ],
        'otp_request_ip' => [
            'max_attempts' => (int) env('OTP_REQUEST_IP_RATE_LIMIT', 50),
            'decay_seconds' => (int) env('OTP_REQUEST_IP_RATE_DECAY', 3600),
        ],
        'otp_verify' => [
            'max_attempts' => (int) env('OTP_VERIFY_RATE_LIMIT', 5),
            'decay_seconds' => (int) env('OTP_VERIFY_RATE_DECAY', 600),
        ],
        'login' => [
            'max_attempts' => (int) env('AUTH_LOGIN_RATE_LIMIT', 5),
            'decay_seconds' => (int) env('AUTH_LOGIN_RATE_DECAY', 60),
        ],
        'login_ip' => [
            'max_attempts' => (int) env('AUTH_LOGIN_IP_RATE_LIMIT', 20),
            'decay_seconds' => (int) env('AUTH_LOGIN_IP_RATE_DECAY', 60),
        ],
    ],

    'mock' => [
        'expose_code' => (bool) env('OTP_MOCK_EXPOSE_CODE', false),
    ],

    'smspoh' => [
        'base_url' => env('SMSPOH_BASE_URL', 'https://v3.smspoh.com/api/otp'),
        'api_key' => env('SMSPOH_API_KEY'),
        'api_secret' => env('SMSPOH_API_SECRET'),
        'sender_id' => env('SMSPOH_SENDER_ID'),
        'brand' => env('SMSPOH_BRAND'),
        'template' => env('SMSPOH_TEMPLATE', 'Your {brand} verification code is {code}.'),
    ],
];
