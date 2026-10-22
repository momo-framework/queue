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

use Momo\Queue\Queue\Comparators;
use Momo\Queue\Queue\Envelope;
use Momo\Queue\Queue\PriorityQueue;
use Momo\Queue\Tests\Fixtures\RecordingJob;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PriorityQueueTest extends TestCase
{
    public function test_extract_from_empty_returns_null(): void
    {
        $queue = new PriorityQueue(Comparators::byPriority());

        self::assertNull($queue->extract());
        self::assertNull($queue->peek());
        self::assertTrue($queue->isEmpty());
        self::assertSame(0, $queue->size());
    }

    public function test_higher_priority_leaves_first(): void
    {
        $queue = new PriorityQueue(Comparators::byPriority());

        $queue->insert($this->envelope(seq: 0, priority: 1));
        $queue->insert($this->envelope(seq: 1, priority: 10));
        $queue->insert($this->envelope(seq: 2, priority: 5));

        self::assertSame(10, $this->priorityOf($queue->extract()));
        self::assertSame(5, $this->priorityOf($queue->extract()));
        self::assertSame(1, $this->priorityOf($queue->extract()));
    }

    public function test_equal_priority_preserves_insertion_order(): void
    {
        $queue = new PriorityQueue(Comparators::byPriority());

        $queue->insert($this->envelope(seq: 3, priority: 0));
        $queue->insert($this->envelope(seq: 1, priority: 0));
        $queue->insert($this->envelope(seq: 2, priority: 0));

        self::assertSame(1, $this->sequenceOf($queue->extract()));
        self::assertSame(2, $this->sequenceOf($queue->extract()));
        self::assertSame(3, $this->sequenceOf($queue->extract()));
    }

    public function test_heap_stays_ordered_under_many_insertions(): void
    {
        $queue = new PriorityQueue(Comparators::byPriority());
        $priorities = [4, 9, 1, 7, 2, 8, 0, 6, 3, 5];

        foreach ($priorities as $seq => $priority) {
            $queue->insert($this->envelope(seq: $seq, priority: $priority));
        }

        $extracted = [];
        while (($envelope = $queue->extract()) instanceof Envelope) {
            $extracted[] = $envelope->priority;
        }

        self::assertSame([9, 8, 7, 6, 5, 4, 3, 2, 1, 0], $extracted);
    }

    /**
     * @param int<0, max> $seq
     */
    private function envelope(int $seq, int $priority): Envelope
    {
        return new Envelope(
            id: 'id-' . $seq,
            job: new RecordingJob(),
            priority: $priority,
            availableAt: 0,
            attempts: 0,
            sequence: $seq,
        );
    }

    private function priorityOf(Envelope|null $envelope): int
    {
        self::assertNotNull($envelope);

        return $envelope->priority;
    }

    private function sequenceOf(Envelope|null $envelope): int
    {
        self::assertNotNull($envelope);

        return $envelope->sequence;
    }
}
