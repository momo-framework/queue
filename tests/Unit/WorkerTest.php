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

namespace Momo\Queue\Tests\Unit;

use Momo\Queue\Backoff\ConstantBackoffPolicy;
use Momo\Queue\Events\JobFailed;
use Momo\Queue\Events\JobProcessed;
use Momo\Queue\Events\JobProcessing;
use Momo\Queue\FailedJob\InMemoryFailedJobStore;
use Momo\Queue\Queue\InMemoryQueue;
use Momo\Queue\Support\MutableClock;
use Momo\Queue\Tests\Fixtures\AlwaysFailingJob;
use Momo\Queue\Tests\Fixtures\FlakyJob;
use Momo\Queue\Tests\Fixtures\HostileJob;
use Momo\Queue\Tests\Fixtures\RecordingJob;
use Momo\Queue\Tests\Fixtures\SpyEventBus;
use Momo\Queue\Worker\NullSleeper;
use Momo\Queue\Worker\Worker;
use Momo\Queue\Worker\WorkerOptions;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class WorkerTest extends TestCase
{
    private MutableClock $clock;

    private InMemoryQueue $queue;

    private InMemoryFailedJobStore $failed;

    private SpyEventBus $events;

    private Worker $worker;

    protected function setUp(): void
    {
        $this->clock = new MutableClock(0);
        $this->queue = new InMemoryQueue($this->clock);
        $this->failed = new InMemoryFailedJobStore($this->clock);
        $this->events = new SpyEventBus();
        $this->worker = new Worker(
            $this->queue,
            $this->failed,
            new ConstantBackoffPolicy(0),
            new NullSleeper(),
            $this->events,
        );
    }

    public function test_run_once_returns_false_when_queue_empty(): void
    {
        self::assertFalse($this->worker->runOnce());
    }

    public function test_successful_job_runs_and_emits_events(): void
    {
        $job = new RecordingJob();
        $this->queue->push($job);

        self::assertTrue($this->worker->runOnce());
        self::assertSame(1, $job->runs);
        self::assertSame(0, $this->queue->size());
        self::assertSame(1, $this->events->countOf(JobProcessing::class));
        self::assertSame(1, $this->events->countOf(JobProcessed::class));
        self::assertSame(0, $this->events->countOf(JobFailed::class));
    }

    public function test_transient_failure_is_retried_until_success(): void
    {
        $job = new FlakyJob(failTimes: 2);
        $this->queue->push($job);

        $this->worker->run(new WorkerOptions(stopWhenEmpty: true));

        self::assertSame(3, $job->attempts);
        self::assertSame(0, $this->queue->size());
        self::assertSame([], $this->failed->all());
        self::assertSame(1, $this->events->countOf(JobProcessed::class));
        self::assertSame(3, $this->events->countOf(JobProcessing::class));
    }

    public function test_exhausted_job_moves_to_failed_store_and_calls_failed(): void
    {
        $job = new AlwaysFailingJob();
        $this->queue->push($job);

        $this->worker->run(new WorkerOptions(stopWhenEmpty: true));

        self::assertSame(1, $job->failedCalls);
        self::assertNotNull($job->lastException);
        self::assertSame(0, $this->queue->size());

        $records = $this->failed->all();
        self::assertCount(1, $records);
        self::assertSame(AlwaysFailingJob::class, $records[0]->jobClass);
        self::assertSame(3, $records[0]->attempts);
        self::assertSame(1, $this->events->countOf(JobFailed::class));
        self::assertSame(3, $this->events->countOf(JobProcessing::class));
    }

    public function test_worker_survives_job_whose_failed_handler_throws(): void
    {
        $this->queue->push(new HostileJob());

        $this->worker->run(new WorkerOptions(stopWhenEmpty: true));

        self::assertCount(1, $this->failed->all());
        self::assertSame(1, $this->events->countOf(JobFailed::class));
    }

    public function test_max_jobs_limit_stops_the_loop(): void
    {
        $this->queue->push(new RecordingJob());
        $this->queue->push(new RecordingJob());
        $this->queue->push(new RecordingJob());

        $this->worker->run(new WorkerOptions(maxJobs: 2));

        self::assertSame(1, $this->queue->size());
    }
}
