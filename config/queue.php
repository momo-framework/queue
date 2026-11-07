<?php

declare(strict_types=1);

use Momo\Kernel\Support\Env;

// ┌───────────────────────────────────────────────────────────────────────────┐
// │ QUEUE                                                                     │
// │ Jobs and queued commands of the app on RabbitMQ.                          │
// └───────────────────────────────────────────────────────────────────────────┘

return [
    // ◈ QUEUES ────────────────────────────────────────────────────────────────
    // The queue jobs go to, how long a taken job may run, where failed ones wait.
    'queue' => Env::string('QUEUE_NAME', 'default'),
    'retry_after' => Env::int('QUEUE_RETRY_AFTER', 90, min: 1),
    'failed_queue' => Env::string('QUEUE_FAILED', 'momo.failed'),

    // ◈ CONNECTION ────────────────────────────────────────────────────────────
    // Where the app reaches RabbitMQ. The password is a secret in production.
    'host' => Env::string('AMQP_HOST', 'rabbitmq'),
    'port' => Env::int('AMQP_PORT', 5672, min: 1, max: 65535),
    'user' => Env::string('AMQP_USER', 'guest'),
    'password' => Env::secret('AMQP_PASSWORD', 'guest'),
    'vhost' => Env::string('AMQP_VHOST', '/'),

    // ◈ LINK ──────────────────────────────────────────────────────────────────
    // Heartbeat and timeouts of a connection, and connections per worker.
    'heartbeat' => Env::int('AMQP_HEARTBEAT', 30, min: 0),
    'timeout' => Env::float('AMQP_TIMEOUT', 5.0, min: 0.1),
    'pool' => Env::int('AMQP_POOL', 32, min: 1),
];
