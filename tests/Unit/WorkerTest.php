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
use Momo\Queue\Exceptions\QueueException;
use Momo\Queue\Handlers;
use Momo\Queue\Job;
use Momo\Queue\Tests\Fixtures\Billing\Application\SendInvoice;
use Momo\Queue\Tests\Fixtures\Billing\Presentation\Queue\SendInvoiceHandler;
use Momo\Queue\Tests\Fixtures\FlakyTransport;
use Momo\Queue\Tests\Fixtures\Keys;
use Momo\Queue\Transport\MemoryTransport;
use Momo\Queue\Worker;
use Momo\Queue\WorkerOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
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
    public function a_dispatched_job_goes_to_the_queue_of_its_attribute_and_runs_in_the_worker(): void
    {
        $queue = new Dispatcher($this->transport, Keys::signer());
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
        new Dispatcher($this->transport, Keys::signer())->dispatch(new SendInvoice(3, fail: true));
        $worker = new Worker($this->transport, $this->handlers, Keys::signer(), static function (string $status, Envelope|null $e) use (&$log): void {
            $log[] = $status . ' #' . $e?->attempts;
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
    public function a_job_signed_with_another_key_is_never_opened_or_retried(): void
    {
        $log = [];
        new Dispatcher($this->transport, Keys::signer('stolen-broker-password'))->dispatch(new SendInvoice(6));
        $worker = new Worker($this->transport, $this->handlers, Keys::signer(), static function (string $status, Envelope|null $e) use (&$log): void {
            $log[] = $status . ' #' . $e?->attempts;
        });

        $worker->run(new WorkerOptions(queues: ['mail'], stopWhenEmpty: true));

        self::assertSame(['failed #1'], $log);
        self::assertSame([], SendInvoiceHandler::$sent);
        self::assertStringContainsString('is not signed by this app', $this->transport->failed()[0]->error);
    }

    #[Test]
    public function a_changed_payload_breaks_the_signature(): void
    {
        $signed = Envelope::wrap(new SendInvoice(7), 'mail', Keys::signer());
        $forged = Envelope::fromJson(str_replace(base64_encode($signed->payload), base64_encode(serialize(new SendInvoice(8))), $signed->toJson()));

        self::assertEquals(new SendInvoice(7), Envelope::fromJson($signed->toJson())->job(Keys::signer()));

        $this->expectException(QueueException::class);

        $forged->job(Keys::signer());
    }

    #[Test]
    public function a_job_whose_worker_died_reappears_after_its_visibility_timeout(): void
    {
        new Dispatcher($this->transport, Keys::signer())->dispatch(new SendInvoice(4));

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
        $queue = new Dispatcher($this->transport, Keys::signer());
        for ($i = 0; $i < 10; $i++) {
            $queue->dispatch(new SendInvoice($i, takes: 0.1));
        }

        $started = microtime(true);
        $done = $this->worker()->run(new WorkerOptions(queues: ['mail'], concurrency: 5, stopWhenEmpty: true, idle: 0.01));

        self::assertSame(10, $done);
        self::assertLessThan(0.35, microtime(true) - $started);
    }

    #[Test]
    public function a_lost_ack_is_not_a_failure_of_the_job(): void
    {
        $flaky = new FlakyTransport($this->transport);
        $flaky->ackFailures = 1;
        new Dispatcher($flaky, Keys::signer())->dispatch(new SendInvoice(5));
        $log = [];

        $worker = new Worker($flaky, $this->handlers, Keys::signer(), static function (string $status) use (&$log): void {
            $log[] = $status;
        });
        $worker->run(new WorkerOptions(queues: ['mail'], stopWhenEmpty: true, idle: 0.0));

        self::assertSame([5], SendInvoiceHandler::$sent);
        self::assertSame(['error'], $log);
    }

    #[Test]
    public function the_worker_outlives_a_broker_that_is_down_for_a_while(): void
    {
        $flaky = new FlakyTransport($this->transport);
        new Dispatcher($flaky, Keys::signer())->dispatch(new SendInvoice(9));
        $flaky->popFailures = 2;
        $log = [];

        $worker = new Worker($flaky, $this->handlers, Keys::signer(), static function (string $status) use (&$log): void {
            $log[] = $status;
        });
        $worker->run(new WorkerOptions(queues: ['mail'], stopWhenEmpty: true, idle: 0.001));

        self::assertSame(['error', 'error', 'processed'], $log);
        self::assertSame([9], SendInvoiceHandler::$sent);
    }

    #[Test]
    public function the_worker_gives_up_on_a_broker_that_stays_down_so_the_supervisor_restarts_it(): void
    {
        $flaky = new FlakyTransport($this->transport);
        $flaky->popFailures = 100;

        $log = [];

        $worker = new Worker($flaky, $this->handlers, Keys::signer(), static function (string $status) use (&$log): void {
            $log[] = $status;
        });
        $worker->run(new WorkerOptions(queues: ['mail'], idle: 0.0001, maxFailures: 3));

        self::assertTrue($worker->lostBroker());
        self::assertSame(['error', 'error', 'error'], $log);
        self::assertSame(97, $flaky->popFailures);
    }

    private function worker(): Worker
    {
        return new Worker($this->transport, $this->handlers, Keys::signer());
    }
}
