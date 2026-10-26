<?php

declare(strict_types=1);

return [
    'transport' => env('QUEUE_CONNECTION', 'sync'),
    'queue' => 'default',
    'redis' => [
        'connection' => env('QUEUE_REDIS_CONNECTION', 'default'),
        'prefix' => 'queue:',
    ],
    'database' => [
        'connection' => env('QUEUE_DB_CONNECTION'),
        'table' => 'jobs',
    ],
    'retry_after' => 90,
];
