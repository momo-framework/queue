<?php

/**
 * Part of Momo Framework.
 *
 * © Momo Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author    Vahe Sargsyan <w33bvGL>
 * @copyright Momo Framework
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
 */

declare(strict_types=1);

namespace Momo\Queue\Tests\Unit;

use Momo\Queue\Queue\InMemoryQueue;
use Momo\Queue\Support\MutableClock;
use Momo\Queue\Tests\Fixtures\RecordingJob;
use PHPUnit\Framework\TestCase;

final class InMemoryQueueTest extends TestCase
{
    public function testPopReturnsNullWhenEmpty(): void
    {
        $queue = new InMemoryQueue(new MutableClock(0));

        self::assertNull($queue->pop());
        self::assertSame(0, $queue->size());
    }

    public function testPushReturnsNonEmptyId(): void
    {
        $queue = new InMemoryQueue(new MutableClock(0));

        $id = $queue->push(new RecordingJob());

        self::assertNotSame('', $id);
        self::assertSame(1, $queue->size());
    }

    public function testHigherPriorityPopsFirst(): void
    {
        $queue = new InMemoryQueue(new MutableClock(0));

        $low  = $queue->push(new RecordingJob(), priority: 1);
        $high = $queue->push(new RecordingJob(), priority: 10);

        $first = $queue->pop();
        self::assertNotNull($first);
        self::assertSame($high, $first->id);

        $second = $queue->pop();
        self::assertNotNull($second);
        self::assertSame($low, $second->id);
    }

    public function testDelayedJobIsNotReturnedEarly(): void
    {
        $clock = new MutableClock(1_000);
        $queue = new InMemoryQueue($clock);

        $queue->later(60, new RecordingJob());

        self::assertNull($queue->pop());
        self::assertSame(1, $queue->size());
    }

    public function testDelayedJobBecomesAvailableAfterClockAdvances(): void
    {
        $clock = new MutableClock(1_000);
        $queue = new InMemoryQueue($clock);

        $id = $queue->later(60, new RecordingJob());

        $clock->advance(60);

        $envelope = $queue->pop();
        self::assertNotNull($envelope);
        self::assertSame($id, $envelope->id);
    }

    public function testReadyJobOutranksDelayedRegardlessOfPriority(): void
    {
        $clock = new MutableClock(0);
        $queue = new InMemoryQueue($clock);

        $queue->later(100, new RecordingJob(), priority: 99);
        $ready = $queue->push(new RecordingJob(), priority: 0);

        $envelope = $queue->pop();
        self::assertNotNull($envelope);
        self::assertSame($ready, $envelope->id);
    }

    public function testReleaseRequeuesWithDelay(): void
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
