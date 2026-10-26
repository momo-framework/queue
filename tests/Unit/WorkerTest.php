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

use Momo\Queue\Dispatcher;
use Momo\Queue\Envelope;
use Momo\Queue\Handlers;
use Momo\Queue\Job;
use Momo\Queue\Tests\Fixtures\Billing\Application\SendInvoice;
use Momo\Queue\Tests\Fixtures\Billing\Presentation\Queue\SendInvoiceHandler;
use Momo\Queue\Transport\MemoryTransport;
use Momo\Queue\Worker;
use Momo\Queue\WorkerOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Worker::class)]
#[CoversClass(Dispatcher::class)]
#[CoversClass(MemoryTransport::class)]
#[CoversClass(Envelope::class)]
#[CoversClass(Job::class)]
#[CoversClass(Handlers::class)]
final class WorkerTest extends TestCase
{
    private int $now = 1_000;

    private MemoryTransport $transport;

    private Handlers $handlers;

    protected function setUp(): void
    {
        SendInvoiceHandler::$sent = [];
        $this->transport = new MemoryTransport(fn (): int => $this->now);
        $this->handlers = new Handlers([new SendInvoiceHandler()]);
    }

    #[Test]
    public function without_a_transport_the_handler_runs_on_dispatch(): void
    {
        new Dispatcher(null, $this->handlers)->dispatch(new SendInvoice(7));

        self::assertSame([7], SendInvoiceHandler::$sent);
    }

    #[Test]
    public function a_dispatched_job_goes_to_the_queue_of_its_attribute_and_runs_in_the_worker(): void
    {
        $queue = new Dispatcher($this->transport, $this->handlers);
        $queue->dispatch(new SendInvoice(1));
        $queue->dispatch(new SendInvoice(2), delay: 60);

        self::assertSame(2, $this->transport->size('mail'));
        self::assertSame(1, $this->worker()->run(new WorkerOptions(queues: ['mail'], stopWhenEmpty: true)));

        $this->now += 60;
        $this->worker()->run(new WorkerOptions(queues: ['mail'], stopWhenEmpty: true));

        self::assertSame([1, 2], SendInvoiceHandler::$sent);
    }

    #[Test]
    public function a_failing_job_is_retried_then_kept_as_failed_and_can_be_retried(): void
    {
        $log = [];
        new Dispatcher($this->transport, $this->handlers)->dispatch(new SendInvoice(3, fail: true));
        $worker = new Worker($this->transport, $this->handlers, static function (string $status, Envelope $e) use (&$log): void {
            $log[] = $status . ' #' . $e->attempts;
        });

        $worker->run(new WorkerOptions(queues: ['mail'], stopWhenEmpty: true));

        self::assertSame(['retried #1', 'failed #2'], $log);
        self::assertCount(1, $this->transport->failed());
        self::assertStringContainsString('SMTP refused', $this->transport->failed()[0]->error);

        self::assertTrue($this->transport->retry($this->transport->failed()[0]->envelope->id));
        self::assertSame([], $this->transport->failed());
        self::assertSame(1, $this->transport->size('mail'));
    }

    #[Test]
    public function a_job_whose_worker_died_reappears_after_its_visibility_timeout(): void
    {
        new Dispatcher($this->transport, $this->handlers)->dispatch(new SendInvoice(4));

        self::assertInstanceOf(Envelope::class, $this->transport->pop('mail', 30));
        self::assertNull($this->transport->pop('mail', 30));

        $this->now += 31;
        $again = $this->transport->pop('mail', 30);
        self::assertInstanceOf(Envelope::class, $again);
        self::assertSame(2, $again->attempts);
    }

    #[Test]
    public function backoff_follows_the_attribute_then_repeats_its_last_value(): void
    {
        $job = new Job(backoff: [5, 30]);

        self::assertSame([5, 30, 30], [$job->delayBefore(2), $job->delayBefore(3), $job->delayBefore(4)]);
        self::assertLessThanOrEqual(8, new Job()->delayBefore(3));
    }

    #[Test]
    #[RequiresPhpExtension('swoole')]
    public function jobs_run_concurrently_in_coroutines(): void
    {
        $queue = new Dispatcher($this->transport, $this->handlers);
        for ($i = 0; $i < 10; $i++) {
            $queue->dispatch(new SendInvoice($i, takes: 0.1));
        }

        $started = microtime(true);
        $done = $this->worker()->run(new WorkerOptions(queues: ['mail'], concurrency: 5, stopWhenEmpty: true, idle: 0.01));

        self::assertSame(10, $done);
        self::assertLessThan(0.35, microtime(true) - $started);
    }

    private function worker(): Worker
    {
        return new Worker($this->transport, $this->handlers);
    }
}
