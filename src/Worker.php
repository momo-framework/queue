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

use Closure;
use Momo\Queue\Contracts\Transport;
use Swoole\Coroutine;

use function Swoole\Coroutine\run;

use Swoole\Coroutine\WaitGroup;
use Throwable;

/**
 * Takes jobs from the transport and runs their handlers. With Swoole,
 * $concurrency coroutines take jobs in parallel inside one process, so a
 * worker waiting on HTTP or SQL keeps the others busy. stop() lets the
 * running jobs finish and takes no new ones.
 */
final class Worker
{
    private bool $running = false;

    private int $done = 0;

    /**
     * @param null|Closure(string, Envelope, null|Throwable, float): void $report called after every job:
     *                                                                            processed, retried or failed
     */
    public function __construct(
        private readonly Transport $transport,
        private readonly Handlers $handlers,
        private readonly Closure|null $report = null,
    ) {}

    /**
     * @return int jobs processed
     */
    public function run(WorkerOptions $options): int
    {
        $this->running = true;
        $this->done = 0;

        if (! extension_loaded('swoole') || $options->concurrency <= 1) {
            $this->loop($options);

            return $this->done;
        }

        $work = function () use ($options): void {
            $group = new WaitGroup();
            for ($i = 0; $i < $options->concurrency; $i++) {
                $group->add();
                Coroutine::create(function () use ($options, $group): void {
                    try {
                        $this->loop($options);
                    } finally {
                        $group->done();
                    }
                });
            }

            $group->wait();
        };

        Coroutine::getCid() > 0 ? $work() : run($work);

        return $this->done;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Takes and runs one job of the first non-empty queue.
     *
     * @param list<string> $queues
     */
    public function once(array $queues, int $visibility = 90): bool
    {
        foreach ($queues as $queue) {
            $envelope = $this->transport->pop($queue, $visibility);
            if ($envelope instanceof Envelope) {
                $this->process($envelope);

                return true;
            }
        }

        return false;
    }

    private function loop(WorkerOptions $options): void
    {
        while ($this->running) {
            if ($options->memoryMb > 0 && memory_get_usage(true) > $options->memoryMb * 1024 * 1024) {
                $this->stop();

                break;
            }

            if ($this->once($options->queues, $options->visibility)) {
                $this->done++;
                if ($options->maxJobs > 0 && $this->done >= $options->maxJobs) {
                    $this->stop();
                }

                continue;
            }

            if ($options->stopWhenEmpty) {
                break;
            }

            $this->sleep($options->idle);
        }
    }

    private function process(Envelope $envelope): void
    {
        $started = hrtime(true);

        try {
            ($this->handlers->of($envelope->class))($envelope->job());
            $this->transport->ack($envelope);
            $this->report('processed', $envelope, null, $started);
        } catch (Throwable $throwable) {
            $job = Dispatcher::attributeOf($envelope->class);
            if ($envelope->attempts >= $job->attempts) {
                $this->transport->fail($envelope, $throwable);
                $this->report('failed', $envelope, $throwable, $started);

                return;
            }

            $this->transport->release($envelope, $job->delayBefore($envelope->attempts + 1));
            $this->report('retried', $envelope, $throwable, $started);
        }
    }

    private function report(string $status, Envelope $envelope, Throwable|null $error, float|int $started): void
    {
        if ($this->report instanceof Closure) {
            ($this->report)($status, $envelope, $error, (hrtime(true) - $started) / 1e6);
        }
    }

    private function sleep(float $seconds): void
    {
        if (extension_loaded('swoole') && Coroutine::getCid() > 0) {
            Coroutine::sleep($seconds);

            return;
        }

        usleep((int) ($seconds * 1_000_000));
    }
}
