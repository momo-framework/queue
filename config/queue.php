<?php

declare(strict_types=1);

use Momo\Kernel\Support\Env;

return [
    'transport' => Env::oneOf('QUEUE_CONNECTION', ['sync', 'redis', 'database'], 'sync'),
    'queue' => 'default',
    'redis' => [
        'connection' => Env::string('QUEUE_REDIS_CONNECTION', 'default'),
        'prefix' => 'queue:',
    ],
    'database' => [
        'connection' => Env::string('QUEUE_DB_CONNECTION'),
        'table' => 'jobs',
    ],
    'retry_after' => 90,
];
