<?php

/**
 * This file is part of Momo Framework.
 *
 * (c) Vahe Sargsyan <w33bvGL>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license AGPL-3.0-or-later
 * @link    https://gitlab.com/anidzen/momo
 */

declare(strict_types=1);

namespace Momo\Queue\Amqp;

use Momo\Kernel\Pool\Pool;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

final readonly class Connections
{
    /** @var Pool<Link> */
    private Pool $pool;

    /** @param array{host: string, port: int, user: string, password: string, vhost: string, heartbeat: int, timeout: float, pool: int} $settings */
    public function __construct(array $settings)
    {
        $this->pool = new Pool(
            'amqp',
            static function () use ($settings): Link {
                $connection = new AMQPStreamConnection(
                    $settings['host'],
                    $settings['port'],
                    $settings['user'],
                    $settings['password'],
                    $settings['vhost'],
                    connection_timeout: $settings['timeout'],
                    read_write_timeout: max($settings['timeout'], $settings['heartbeat'] * 2.0),
                    heartbeat: $settings['heartbeat'],
                );

                return new Link($connection, $connection->channel(), $settings['timeout']);
            },
            max(1, $settings['pool']),
            alive: static fn (Link $link): bool => $link->connection->isConnected(),
        );
    }

    public function link(): Link
    {
        return $this->pool->get();
    }

    public function discard(): void
    {
        try {
            $this->link()->close();
        } catch (Throwable) {
        }

        $this->pool->discard();
    }

    public function close(): void
    {
        $this->pool->close();
    }
}
