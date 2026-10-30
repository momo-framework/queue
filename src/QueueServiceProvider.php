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

namespace Momo\Queue;

use LogicException;
use Momo\Database\DatabaseManager;
use Momo\Database\Migrations\Migrator;
use Momo\Kernel\Support\ServiceProvider;
use Momo\Queue\Contracts\JobHandler;
use Momo\Queue\Contracts\Queue;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Transport\DatabaseTransport;
use Momo\Queue\Transport\MemoryTransport;
use Momo\Queue\Transport\MissingTransport;
use Momo\Queue\Transport\RedisTransport;
use Momo\Redis\RedisManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

final class QueueServiceProvider extends ServiceProvider
{
    /** The protocol this package declares in extra.momo.protocols. */
    public const string PROTOCOL = 'Queue';

    public const string HANDLER_TAG = 'momo.queue.handler';

    public function register(): void
    {
        $container = $this->getContainerBuilder();
        $transport = $this->string('queue.transport', 'sync');

        match ($transport) {
            'sync' => null,
            'memory' => $container->register(Transport::class, MemoryTransport::class),
            'redis' => $this->redis(),
            'database' => $this->database(),
            // Another package brings this transport (amqp from momo-amqp) and
            // registers it before or after this provider. Until it does,
            // every call fails with the reason, instead of running jobs inline.
            default => $container->hasDefinition(Transport::class) ? null : $container->register(Transport::class, MissingTransport::class)->setArguments([$transport]),
        };
        if ($container->hasDefinition(Transport::class)) {
            $container->getDefinition(Transport::class)->setPublic(true);
        }

        $maybeTransport = new Reference(Transport::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $this->tag(JobHandler::class, self::HANDLER_TAG);
        $container->register(Handlers::class, Handlers::class)->setArguments([$this->tagged(self::HANDLER_TAG)])->setPublic(true);

        $default = $this->string('queue.queue', 'default');
        $container->register(Dispatcher::class, Dispatcher::class)
            ->setArguments([$maybeTransport, new Reference(Handlers::class), $default])
            ->setPublic(true);
        $container->setAlias(Queue::class, Dispatcher::class)->setPublic(true);

        $retry = $this->app->getConfig('queue.retry_after', 90);
        foreach ([
            Console\QueueWorkCommand::class => [$maybeTransport, new Reference(Handlers::class), is_numeric($retry) ? (int) $retry : 90],
            Console\QueueFailedCommand::class => [$maybeTransport],
            Console\QueueRetryCommand::class => [$maybeTransport],
            Console\QueueListCommand::class => [new Reference(Handlers::class), $default],
        ] as $command => $arguments) {
            $container->register($command, $command)->setArguments($arguments)->addTag('momo.console_command')->setPublic(true);
        }
    }

    /**
     * The jobs and failed_jobs tables come with the package and join
     * migrate when the database transport is used.
     */
    public function boot(): void
    {
        if ($this->string('queue.transport', 'sync') === 'database' && class_exists(Migrator::class)) {
            $this->app->make(Migrator::class)->addPath(__DIR__ . '/../database/migrations');
        }
    }

    private function redis(): void
    {
        if (! class_exists(RedisManager::class)) {
            throw new LogicException('QUEUE_CONNECTION is redis: composer require anidzen/momo-redis.');
        }

        $container = $this->getContainerBuilder();
        $container->register('momo.queue.redis', \Momo\Redis\Connection::class)
            ->setFactory([new Reference(RedisManager::class), 'connection'])
            ->setArguments([$this->string('queue.redis.connection', 'default')]);
        $container->register(Transport::class, RedisTransport::class)
            ->setArguments([new Reference('momo.queue.redis'), $this->string('queue.redis.prefix', 'queue:')]);
    }

    private function database(): void
    {
        if (! class_exists(DatabaseManager::class)) {
            throw new LogicException('QUEUE_CONNECTION is database: composer require anidzen/momo-database.');
        }

        $connection = $this->app->getConfig('queue.database.connection');
        $container = $this->getContainerBuilder();
        $container->register('momo.queue.db', \Momo\Database\Connection::class)
            ->setFactory([new Reference(DatabaseManager::class), 'connection'])
            ->setArguments([is_string($connection) && $connection !== '' ? $connection : null]);
        $container->register(Transport::class, DatabaseTransport::class)
            ->setArguments([new Reference('momo.queue.db'), $this->string('queue.database.table', 'jobs')]);
    }

    private function string(string $key, string $default): string
    {
        $value = $this->app->getConfig($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
