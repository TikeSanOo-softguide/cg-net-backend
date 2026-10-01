<?php

return [
    // Larger chunks are fine after switching PIN hashing to HMAC-SHA256.
    'chunk_size' => (int) env('TOPUP_CARDS_CHUNK_SIZE', 2000),
    'max_cards' => (int) env('TOPUP_CARDS_MAX_CARDS', 100000),
    'preview_limit' => (int) env('TOPUP_CARDS_PREVIEW_LIMIT', 100),
    // Optional; when empty, a stable HMAC pepper is derived from APP_KEY.
    'pin_pepper' => (string) env('TOPUP_CARD_PIN_PEPPER', ''),
    'rate_limit_per_minute' => (int) env('TOPUP_CARDS_RATE_LIMIT_PER_MINUTE', 5),
    'redemption' => [
        'max_wallet_balance' => (int) env('TOPUP_REDEEM_MAX_WALLET_BALANCE', 100000000),
        'failed_pin_limits' => [
            'user' => [
                'max_attempts' => (int) env('TOPUP_REDEEM_FAILED_PIN_USER_MAX_ATTEMPTS', 10),
                'decay_seconds' => (int) env('TOPUP_REDEEM_FAILED_PIN_USER_DECAY_SECONDS', 1800),
            ],
            'ip' => [
                'max_attempts' => (int) env('TOPUP_REDEEM_FAILED_PIN_IP_MAX_ATTEMPTS', 20),
                'decay_seconds' => (int) env('TOPUP_REDEEM_FAILED_PIN_IP_DECAY_SECONDS', 3600),
            ],
        ],
    ],
];
