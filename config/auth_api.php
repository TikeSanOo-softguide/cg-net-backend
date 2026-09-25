<?php

return [
    'token_ttl_days' => (int) env('API_TOKEN_TTL_DAYS', 30),
    'access_token_ttl_minutes' => (int) env('API_ACCESS_TOKEN_TTL_MINUTES', 15),
    'refresh_token_ttl_days' => (int) env('API_REFRESH_TOKEN_TTL_DAYS', 7),
    'scopes' => ['user:read', 'requests:write', 'profile:write'],
];
