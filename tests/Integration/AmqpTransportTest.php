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

namespace Momo\Queue\Tests\Integration;

use Momo\Queue\Amqp\AmqpTransport;
use Momo\Queue\Amqp\Connections;
use Momo\Queue\Envelope;
use Momo\Queue\Tests\Fixtures\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/** @internal */
#[CoversClass(AmqpTransport::class)]
#[CoversClass(Connections::class)]
final class AmqpTransportTest extends TestCase
{
    private Connections $connections;

    private AmqpTransport $transport;

    private string $queue = '';

    protected function setUp(): void
    {
        $host = getenv('AMQP_HOST');
        if ($host === false) {
            self::markTestSkipped('Set AMQP_HOST to run against RabbitMQ.');
        }

        $this->connections = new Connections(['host' => $host, 'port' => 5672, 'user' => 'guest', 'password' => 'guest', 'vhost' => '/', 'heartbeat' => 30, 'timeout' => 5.0, 'pool' => 4]);
        $this->queue = 'test.' . bin2hex(random_bytes(4));
        $this->transport = new AmqpTransport($this->connections, $this->queue . '.failed');
    }

    protected function tearDown(): void
    {
        if (isset($this->connections)) {
            $channel = $this->connections->link()->channel;
            foreach ([$this->queue, $this->queue . '.failed', $this->queue . '.delay.1'] as $q) {
                $channel->queue_delete($q);
            }

            $this->connections->close();
        }
    }

    #[Test]
    public function a_job_is_pushed_taken_and_acked(): void
    {
        $this->transport->push($this->envelope('a'));
        self::assertSame(1, $this->transport->size($this->queue));

        $taken = $this->transport->pop($this->queue, 60);
        self::assertInstanceOf(Envelope::class, $taken);
        self::assertSame(1, $taken->attempts);
        self::assertSame('a', $this->job($taken)->name);

        $this->transport->ack($taken);
        self::assertNull($this->transport->pop($this->queue, 60));
        self::assertSame(0, $this->transport->size($this->queue));
    }

    #[Test]
    public function a_delayed_job_waits_in_its_delay_queue(): void
    {
        $this->transport->push($this->envelope('later'), 1);

        self::assertNull($this->transport->pop($this->queue, 60));
        usleep(1_500_000);

        $taken = $this->transport->pop($this->queue, 60);
        self::assertInstanceOf(Envelope::class, $taken);
        self::assertSame('later', $this->job($taken)->name);
        $this->transport->ack($taken);
    }

    #[Test]
    public function a_released_job_comes_back_with_its_attempts(): void
    {
        $this->transport->push($this->envelope('flaky'));
        $first = $this->transport->pop($this->queue, 60);
        self::assertInstanceOf(Envelope::class, $first);

        $this->transport->release($first, 0);
        $second = $this->transport->pop($this->queue, 60);

        self::assertInstanceOf(Envelope::class, $second);
        self::assertSame(2, $second->attempts);
        $this->transport->ack($second);
    }

    #[Test]
    public function a_job_of_a_dead_worker_is_delivered_again(): void
    {
        $this->transport->push($this->envelope('crash'));
        self::assertInstanceOf(Envelope::class, $this->transport->pop($this->queue, 60));

        $this->connections->discard();

        $again = $this->transport->pop($this->queue, 60);
        self::assertInstanceOf(Envelope::class, $again);
        self::assertSame(2, $again->attempts);
        $this->transport->ack($again);
    }

    #[Test]
    public function a_failed_job_is_listed_retried_and_forgotten(): void
    {
        $this->transport->push($this->envelope('one'));
        $this->transport->push($this->envelope('two'));
        foreach ([1, 2] as $_) {
            $taken = $this->transport->pop($this->queue, 60);
            self::assertInstanceOf(Envelope::class, $taken);
            $this->transport->fail($taken, new RuntimeException('boom'));
        }

        $failed = $this->transport->failed();
        self::assertCount(2, $failed);
        self::assertSame('RuntimeException: boom', $failed[0]->error);
        self::assertCount(2, $this->transport->failed());

        self::assertTrue($this->transport->retry($failed[0]->envelope->id));
        self::assertTrue($this->transport->forget($failed[1]->envelope->id));
        self::assertFalse($this->transport->forget('nope'));
        self::assertSame([], $this->transport->failed());

        $back = $this->transport->pop($this->queue, 60);
        self::assertInstanceOf(Envelope::class, $back);
        self::assertSame(1, $back->attempts);
        self::assertSame('one', $this->job($back)->name);
        $this->transport->ack($back);
    }

    #[Test]
    public function coroutines_take_jobs_side_by_side_each_on_its_own_connection(): void
    {
        if (! extension_loaded('swoole')) {
            self::markTestSkipped('Needs the swoole extension.');
        }

        for ($i = 0; $i < 60; $i++) {
            $this->transport->push($this->envelope('job' . $i));
        }

        $done = [];
        \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
        \Swoole\Coroutine\run(function () use (&$done): void {
            $group = new \Swoole\Coroutine\WaitGroup();
            for ($c = 0; $c < 6; $c++) {
                $group->add();
                \Swoole\Coroutine::create(function () use (&$done, $group): void {
                    while (($taken = $this->transport->pop($this->queue, 60)) instanceof Envelope) {
                        \Swoole\Coroutine::sleep(0.005);
                        $done[] = $this->job($taken)->name;
                        $this->transport->ack($taken);
                    }

                    $group->done();
                });
            }

            $group->wait();
        });
        \Swoole\Runtime::enableCoroutine(0);

        $names = array_values(array_filter($done, is_string(...)));
        self::assertCount(60, $names);
        self::assertCount(60, array_unique($names));
        self::assertSame(0, $this->transport->size($this->queue));
    }

    private function envelope(string $name): Envelope
    {
        $job = new stdClass();
        $job->name = $name;

        return Envelope::wrap($job, $this->queue, Keys::signer());
    }

    private function job(Envelope $envelope): stdClass
    {
        $job = $envelope->job(Keys::signer());
        self::assertInstanceOf(stdClass::class, $job);
        self::assertIsString($job->name ?? null);

        return $job;
    }
}
