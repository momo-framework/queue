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

use Momo\Queue\Queue\InMemoryQueue;
use Momo\Queue\Support\MutableClock;
use Momo\Queue\Tests\Fixtures\RecordingJob;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class InMemoryQueueTest extends TestCase
{
    public function test_pop_returns_null_when_empty(): void
    {
        $queue = new InMemoryQueue(new MutableClock(0));

        self::assertNull($queue->pop());
        self::assertSame(0, $queue->size());
    }

    public function test_push_returns_non_empty_id(): void
    {
        $queue = new InMemoryQueue(new MutableClock(0));

        $id = $queue->push(new RecordingJob());

        self::assertNotSame('', $id);
        self::assertSame(1, $queue->size());
    }

    public function test_higher_priority_pops_first(): void
    {
        $queue = new InMemoryQueue(new MutableClock(0));

        $low = $queue->push(new RecordingJob(), priority: 1);
        $high = $queue->push(new RecordingJob(), priority: 10);

        $first = $queue->pop();
        self::assertNotNull($first);
        self::assertSame($high, $first->id);

        $second = $queue->pop();
        self::assertNotNull($second);
        self::assertSame($low, $second->id);
    }

    public function test_delayed_job_is_not_returned_early(): void
    {
        $clock = new MutableClock(1_000);
        $queue = new InMemoryQueue($clock);

        $queue->later(60, new RecordingJob());

        self::assertNull($queue->pop());
        self::assertSame(1, $queue->size());
    }

    public function test_delayed_job_becomes_available_after_clock_advances(): void
    {
        $clock = new MutableClock(1_000);
        $queue = new InMemoryQueue($clock);

        $id = $queue->later(60, new RecordingJob());

        $clock->advance(60);

        $envelope = $queue->pop();
        self::assertNotNull($envelope);
        self::assertSame($id, $envelope->id);
    }

    public function test_ready_job_outranks_delayed_regardless_of_priority(): void
    {
        $clock = new MutableClock(0);
        $queue = new InMemoryQueue($clock);

        $queue->later(100, new RecordingJob(), priority: 99);

        $ready = $queue->push(new RecordingJob(), priority: 0);

        $envelope = $queue->pop();
        self::assertNotNull($envelope);
        self::assertSame($ready, $envelope->id);
    }

    public function test_release_requeues_with_delay(): void
    {
        $clock = new MutableClock(0);
        $queue = new InMemoryQueue($clock);

        $queue->push(new RecordingJob());

        $envelope = $queue->pop();
        self::assertNotNull($envelope);

        $queue->release($envelope->withAttempts(1), 30);

        self::assertSame(1, $queue->size());

        $clock->advance(30);

        $retried = $queue->pop();
        self::assertNotNull($retried);
        self::assertSame(1, $retried->attempts);
    }
}
