<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Access token (sliding window)
    |--------------------------------------------------------------------------
    |
    | A token stays valid for `idle_ttl_days` after its LAST use. Every
    | authenticated request pushes `expires_at` forward to now + idle_ttl_days,
    | so only a user who is inactive for the full window gets signed out.
    |
    | `touch_interval_minutes` throttles how often the expiry is rewritten, so a
    | busy client does not cause a database write on every request. The effective
    | idle window is therefore idle_ttl_days minus at most this interval.
    |
    */
    'token' => [
        'name' => env('API_TOKEN_NAME', 'flutter'),
        'idle_ttl_days' => (int) env('API_TOKEN_IDLE_TTL_DAYS', 180),
        'touch_interval_minutes' => (int) env('API_TOKEN_TOUCH_INTERVAL_MINUTES', 60),
    ],

    'scopes' => ['user:read', 'requests:write', 'profile:write'],

    /*
    |--------------------------------------------------------------------------
    | Password step for existing accounts
    |--------------------------------------------------------------------------
    |
    | true  : OTP verify -> password -> logged in   (current flow)
    | false : OTP verify -> logged in directly       (password step switched off)
    |
    | New accounts always set a password during registration regardless of this
    | flag, so the password step can be switched back on at any time.
    |
    */
    'require_password_on_login' => (bool) env('AUTH_REQUIRE_PASSWORD_ON_LOGIN', true),

    // Wrong passwords allowed on one OTP verification before it is burned and
    // the user has to request a new OTP.
    'max_password_attempts' => (int) env('AUTH_MAX_PASSWORD_ATTEMPTS', 5),
];
