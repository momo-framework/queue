<?php

/**
 * Part of Momo Framework.
 *
 * © Momo Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Unauthorized copying, modification, or distribution of this file,
 * via any medium, is strictly prohibited without prior written permission
 * from the copyright holder.
 *
 * @author    Vahe Sargsyan <w33bvGL>
 * @copyright Momo Framework
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
 */

declare(strict_types=1);

namespace Momo\Queue\Providers;

use Momo\Events\Contracts\EventBusInterface;
use Momo\Kernel\Support\ServiceProvider;
use Momo\Queue\Backoff\ExponentialBackoffPolicy;
use Momo\Queue\Console\QueueFailedCommand;
use Momo\Queue\Console\QueueWorkCommand;
use Momo\Queue\Contracts\BackoffPolicyInterface;
use Momo\Queue\Contracts\ClockInterface;
use Momo\Queue\Contracts\FailedJobStoreInterface;
use Momo\Queue\Contracts\QueueInterface;
use Momo\Queue\Contracts\SleeperInterface;
use Momo\Queue\Contracts\WorkerInterface;
use Momo\Queue\FailedJob\InMemoryFailedJobStore;
use Momo\Queue\Queue\InMemoryQueue;
use Momo\Queue\Support\SystemClock;
use Momo\Queue\Worker\CoroutineSleeper;
use Momo\Queue\Worker\Worker;

/**
 * Wires the queue engine into the application container.
 *
 * @codeCoverageIgnore
 */
final class QueueServiceProvider extends ServiceProvider
{
    protected array $commands = [
        QueueWorkCommand::class,
        QueueFailedCommand::class,
    ];

    public function register(): void
    {
        $this->registerConfig(__DIR__ . '/../../config/queue.php', 'queue');

        $this->registerDefaultCommands();

        $this->singleton(ClockInterface::class, static fn (): ClockInterface => new SystemClock());

        $this->singleton(SleeperInterface::class, static fn (): SleeperInterface => new CoroutineSleeper());

        $this->singleton(
            QueueInterface::class,
            fn (): QueueInterface => new InMemoryQueue($this->clock()),
        );

        $this->singleton(
            FailedJobStoreInterface::class,
            fn (): FailedJobStoreInterface => new InMemoryFailedJobStore($this->clock()),
        );

        $this->singleton(
            BackoffPolicyInterface::class,
            fn (): BackoffPolicyInterface => $this->buildBackoffPolicy(),
        );

        $this->singleton(
            WorkerInterface::class,
            fn (): WorkerInterface => $this->buildWorker(),
        );
    }

    private function buildWorker(): WorkerInterface
    {
        /** @var QueueInterface $queue */
        $queue = $this->app->make(QueueInterface::class);
        /** @var FailedJobStoreInterface $failedStore */
        $failedStore = $this->app->make(FailedJobStoreInterface::class);
        /** @var BackoffPolicyInterface $backoff */
        $backoff = $this->app->make(BackoffPolicyInterface::class);
        /** @var SleeperInterface $sleeper */
        $sleeper = $this->app->make(SleeperInterface::class);
        /** @var EventBusInterface $events */
        $events = $this->app->make(EventBusInterface::class);

        return new Worker($queue, $failedStore, $backoff, $sleeper, $events);
    }

    private function buildBackoffPolicy(): BackoffPolicyInterface
    {
        $base       = \max(1, $this->intConfig('queue.backoff.base_seconds', 5));
        $max        = \max($base, $this->intConfig('queue.backoff.max_seconds', 3600));
        $multiplier = $this->floatConfig('queue.backoff.multiplier', 2.0);

        return new ExponentialBackoffPolicy(
            baseSeconds: $base,
            maxSeconds: $max,
            multiplier: max($multiplier, 1.0),
        );
    }

    private function clock(): ClockInterface
    {
        /** @var ClockInterface $clock */
        $clock = $this->app->make(ClockInterface::class);

        return $clock;
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->app->getConfig($key, $default);

        return \is_numeric($value) ? (int) $value : $default;
    }

    private function floatConfig(string $key, float $default): float
    {
        $value = $this->app->getConfig($key, $default);

        return \is_numeric($value) ? (float) $value : $default;
    }
}
