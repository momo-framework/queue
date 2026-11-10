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

use Momo\Bus\Contracts\Bus as BusContract;
use Momo\Bus\Contracts\Context;
use Momo\Kernel\Security\Signer;
use Momo\Kernel\Support\ServiceProvider;
use Momo\Queue\Amqp\AmqpTransport;
use Momo\Queue\Amqp\Connections;
use Momo\Queue\Contracts\JobHandler;
use Momo\Queue\Contracts\Queue;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Transport\MemoryTransport;
use Momo\Server\WorkerHooks;
use Symfony\Component\DependencyInjection\Reference;

final class QueueServiceProvider extends ServiceProvider
{
    public const string PROTOCOL = 'Queue';

    public const string HANDLER_TAG = 'momo.queue.handler';

    public const string CONTEXT_TAG = 'momo.bus.context';

    public const string SIGNER = 'momo.queue.signer';

    public function register(): void
    {
        $container = $this->getContainerBuilder();

        $container->register(Connections::class, Connections::class)
            ->setArguments([[
                'host' => $this->string('queue.host', 'rabbitmq'),
                'port' => $this->int('queue.port', 5672),
                'user' => $this->string('queue.user', 'guest'),
                'password' => $this->string('queue.password', 'guest'),
                'vhost' => $this->string('queue.vhost', '/'),
                'heartbeat' => $this->int('queue.heartbeat', 30),
                'timeout' => $this->float('queue.timeout', 5.0),
                'pool' => $this->int('queue.pool', 32),
            ]])
            ->setPublic(true);

        $container->register(AmqpTransport::class, AmqpTransport::class)
            ->setArguments([new Reference(Connections::class), $this->string('queue.failed_queue', 'momo.failed')])
            ->setPublic(true);
        $this->app->getConfig('queue.transport') === 'memory'
            ? $container->register(Transport::class, MemoryTransport::class)->setPublic(true)
            : $container->setAlias(Transport::class, AmqpTransport::class)->setPublic(true);

        $container->register(Health\AmqpCheck::class, Health\AmqpCheck::class)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $this->tag(JobHandler::class, self::HANDLER_TAG);
        $container->register(Handlers::class, Handlers::class)->setArguments([$this->tagged(self::HANDLER_TAG)])->setPublic(true);

        $default = $this->string('queue.queue', 'default');
        $transport = new Reference(Transport::class);
        $container->register(self::SIGNER, Signer::class)
            ->setFactory([new Reference(Signer::class), 'for'])
            ->setArguments(['queue']);
        $signer = new Reference(self::SIGNER);
        $container->register(Dispatcher::class, Dispatcher::class)
            ->setArguments([$transport, $signer, $default])
            ->setPublic(true);
        $container->setAlias(Queue::class, Dispatcher::class)->setPublic(true);

        foreach ([
            Console\QueueWorkCommand::class => [$transport, new Reference(Handlers::class), $signer, $this->int('queue.retry_after', 90)],
            Console\QueueFailedCommand::class => [$transport],
            Console\QueueRetryCommand::class => [$transport],
            Console\QueueListCommand::class => [new Reference(Handlers::class), $default],
        ] as $command => $arguments) {
            $container->register($command, $command)->setArguments($arguments)->addTag('momo.console_command')->setPublic(true);
        }

        $this->tag(Context::class, self::CONTEXT_TAG);
        $container->register(Bus\QueueCommands::class, Bus\QueueCommands::class)
            ->setArguments([new Reference(Queue::class), new Reference(BusContract::class), $this->tagged(self::CONTEXT_TAG)])
            ->setAutoconfigured(true)
            ->setPublic(true);
    }

    public function boot(): void
    {
        $app = $this->app;
        $this->app->make(WorkerHooks::class)->onStop(static function () use ($app): void {
            $app->make(Connections::class)->close();
        });
    }

    private function string(string $key, string $default): string
    {
        $value = $this->app->getConfig($key, $default);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    private function int(string $key, int $default): int
    {
        $value = $this->app->getConfig($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function float(string $key, float $default): float
    {
        $value = $this->app->getConfig($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }
}
