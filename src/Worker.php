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
use Momo\Kernel\Security\Signer;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Exceptions\QueueException;
use Swoole\Coroutine;

use function Swoole\Coroutine\run;

use Swoole\Coroutine\WaitGroup;
use Throwable;

final class Worker
{
    private bool $running = false;

    private int $done = 0;

    private bool $lostBroker = false;

    /** @param null|Closure(string, null|Envelope, null|Throwable, float): void $report */
    public function __construct(
        private readonly Transport $transport,
        private readonly Handlers $handlers,
        private readonly Signer $signer,
        private readonly Closure|null $report = null,
    ) {}

    public function run(WorkerOptions $options): int
    {
        $this->running = true;
        $this->done = 0;
        $this->lostBroker = false;

        if ($options->concurrency <= 1) {
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

    public function lostBroker(): bool
    {
        return $this->lostBroker;
    }

    /** @param list<string> $queues */
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
        $failures = 0;

        while ($this->running) {
            if ($options->memoryMb > 0 && memory_get_usage(true) > $options->memoryMb * 1024 * 1024) {
                $this->stop();

                break;
            }

            try {
                $got = $this->once($options->queues, $options->visibility);
                $failures = 0;
            } catch (Throwable $throwable) {
                $this->report('error', null, $throwable, hrtime(true));
                if ($options->maxFailures > 0 && $failures + 1 >= $options->maxFailures) {
                    $this->lostBroker = true;
                    $this->stop();

                    break;
                }

                $this->sleep(min(5.0, max($options->idle, 0.1) * 2 ** min(++$failures, 6)));

                continue;
            }

            if ($got) {
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
            $payload = $envelope->job($this->signer);
        } catch (QueueException $queueException) {
            $this->transport->fail($envelope, $queueException);
            $this->report('failed', $envelope, $queueException, $started);

            return;
        }

        try {
            ($this->handlers->of($envelope->class))($payload);
        } catch (Throwable $throwable) {
            $job = Dispatcher::jobOf($payload);

            if ($envelope->attempts >= $job->attempts) {
                $this->transport->fail($envelope, $throwable);
                $this->report('failed', $envelope, $throwable, $started);

                return;
            }

            $this->transport->release($envelope, $job->delayBefore($envelope->attempts + 1));
            $this->report('retried', $envelope, $throwable, $started);

            return;
        }

        $this->transport->ack($envelope);
        $this->report('processed', $envelope, null, $started);
    }

    private function report(string $status, Envelope|null $envelope, Throwable|null $error, float|int $started): void
    {
        if ($this->report instanceof Closure) {
            ($this->report)($status, $envelope, $error, (hrtime(true) - $started) / 1e6);
        }
    }

    private function sleep(float $seconds): void
    {
        if (Coroutine::getCid() > 0) {
            Coroutine::sleep($seconds);

            return;
        }

        usleep((int) ($seconds * 1_000_000));
    }
}
