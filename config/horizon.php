<?php

// Tuned for HMAC-SHA256 PIN hashing (not bcrypt). Bottleneck is DB insert +
// sequence locks, so keep concurrency modest and timeouts short.
$topUpCards = [
    'processes' => (int) env('HORIZON_TOPUP_PROCESSES', 4),
    'timeout' => (int) env('HORIZON_TOPUP_TIMEOUT', 180),
    'memory' => (int) env('HORIZON_TOPUP_MEMORY', 200),
];

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', 'horizon:'),
    'middleware' => ['web', 'auth:web'],
    'waits' => [
        'redis:default' => 60,
        'redis:top-up-cards' => 60,
    ],
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    'fast_termination' => false,
    'memory_limit' => 128,
    'top_up_cards' => $topUpCards,
    'defaults' => [
        'supervisor-top-up-cards' => [
            'connection' => 'redis',
            'queue' => ['top-up-cards'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => $topUpCards['processes'],
            'minProcesses' => 1,
            'tries' => 3,
            'timeout' => $topUpCards['timeout'],
            'memory' => $topUpCards['memory'],
            'nice' => 0,
        ],
    ],
    'environments' => [
        'production' => [
            'supervisor-top-up-cards' => [
                'connection' => 'redis',
                'queue' => ['top-up-cards'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => $topUpCards['processes'],
                'minProcesses' => 1,
                'tries' => 3,
                'timeout' => $topUpCards['timeout'],
                'memory' => $topUpCards['memory'],
            ],
        ],
        'local' => [
            'supervisor-top-up-cards' => [
                'connection' => 'redis',
                'queue' => ['top-up-cards'],
                'balance' => 'simple',
                'processes' => max(1, min(2, $topUpCards['processes'])),
                'tries' => 3,
                'timeout' => $topUpCards['timeout'],
                'memory' => $topUpCards['memory'],
            ],
        ],
    ],
];
