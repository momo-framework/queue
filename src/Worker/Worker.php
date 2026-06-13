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

namespace Momo\Queue\Worker;

use Momo\Events\Contracts\EventBusInterface;
use Momo\Queue\Contracts\BackoffPolicyInterface;
use Momo\Queue\Contracts\FailedJobStoreInterface;
use Momo\Queue\Contracts\HasBackoffPolicyInterface;
use Momo\Queue\Contracts\QueueInterface;
use Momo\Queue\Contracts\SleeperInterface;
use Momo\Queue\Contracts\WorkerInterface;
use Momo\Queue\Events\JobFailed;
use Momo\Queue\Events\JobProcessed;
use Momo\Queue\Events\JobProcessing;
use Momo\Queue\Queue\Envelope;
use Throwable;

/**
 * Drains a {@see QueueInterface}, retrying failures with backoff and moving
 * exhausted jobs to the failed-job store. Lifecycle events are published so
 * telemetry and notifications can observe the pipeline without coupling to it.
 */
final class Worker implements WorkerInterface
{
    private bool $running = false;

    public function __construct(
        private readonly QueueInterface $queue,
        private readonly FailedJobStoreInterface $failedStore,
        private readonly BackoffPolicyInterface $defaultBackoff,
        private readonly SleeperInterface $sleeper,
        private readonly EventBusInterface $events,
    ) {
    }

    public function run(WorkerOptions $options): void
    {
        $this->running = true;
        $processed     = 0;

        while ($this->running) {
            if ($this->memoryExceeded($options->memoryLimitMb)) {
                break;
            }

            if ($this->runOnce()) {
                $processed++;

                if ($options->maxJobs > 0 && $processed >= $options->maxJobs) {
                    break;
                }

                continue;
            }

            if ($options->stopWhenEmpty) {
                break;
            }

            $this->sleeper->sleep($options->sleepSeconds);
        }

        $this->running = false;
    }

    public function runOnce(): bool
    {
        $envelope = $this->queue->pop();

        if ($envelope === null) {
            return false;
        }

        $attempt = $envelope->withAttempts($envelope->attempts + 1);

        $this->events->publish(new JobProcessing($attempt));

        try {
            $attempt->job->handle();
            $this->events->publish(new JobProcessed($attempt));
        } catch (Throwable $exception) {
            $this->handleFailure($attempt, $exception);
        }

        return true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function handleFailure(Envelope $attempt, Throwable $exception): void
    {
        if ($attempt->attempts >= $attempt->job->maxAttempts()) {
            $this->failedStore->store($attempt, $exception);
            $this->safelyNotifyJob($attempt, $exception);
            $this->events->publish(new JobFailed($attempt, $exception));

            return;
        }

        $policy = $attempt->job instanceof HasBackoffPolicyInterface
            ? $attempt->job->backoffPolicy()
            : $this->defaultBackoff;

        $this->queue->release($attempt, $policy->delayFor($attempt->attempts + 1));
    }

    private function safelyNotifyJob(Envelope $attempt, Throwable $exception): void
    {
        try {
            $attempt->job->failed($exception);
        } catch (Throwable) {
            // A faulty failed() handler must never crash the worker loop.
        }
    }

    /**
     * @param int<0, max> $limitMb
     */
    private function memoryExceeded(int $limitMb): bool
    {
        if ($limitMb <= 0) {
            return false;
        }

        return \memory_get_usage(true) > $limitMb * 1024 * 1024;
    }
}
