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

namespace Momo\Queue\Queue;

/**
 * Binary heap of {@see Envelope}s ordered by an injected comparator.
 *
 * This is the deterministic PHP fallback for the native `momo_queue` min-heap.
 * The comparator returns a negative number when its first argument must leave
 * the heap before the second; {@see extract()} always returns the minimum
 * under that ordering. A stable monotonic `sequence` on the envelope is the
 * recommended final tie-breaker so equal-priority jobs preserve FIFO order.
 */
final class PriorityQueue
{
    /** @var array<int, Envelope> backing array; index 0 is the heap root */
    private array $heap = [];

    /**
     * @param callable(Envelope, Envelope): int $comparator negative => first leaves first
     */
    public function __construct(
        private readonly mixed $comparator,
    ) {
    }

    /**
     * Insert an envelope, restoring the heap invariant in O(log n).
     */
    public function insert(Envelope $envelope): void
    {
        $this->heap[] = $envelope;
        $this->siftUp(\count($this->heap) - 1);
    }

    /**
     * Remove and return the minimum envelope under the comparator, or null when
     * empty. O(log n).
     */
    public function extract(): ?Envelope
    {
        $count = \count($this->heap);

        if ($count === 0) {
            return null;
        }

        $root = $this->heap[0];
        $last = \array_pop($this->heap);

        if ($count > 1 && $last !== null) {
            $this->heap[0] = $last;
            $this->siftDown(0);
        }

        return $root;
    }

    /**
     * Return the minimum envelope without removing it, or null when empty.
     */
    public function peek(): ?Envelope
    {
        return $this->heap[0] ?? null;
    }

    /**
     * @return int<0, max>
     */
    public function size(): int
    {
        return \count($this->heap);
    }

    public function isEmpty(): bool
    {
        return $this->heap === [];
    }

    private function siftUp(int $index): void
    {
        while ($index > 0) {
            $parent = \intdiv($index - 1, 2);

            if ($this->compare($index, $parent) >= 0) {
                break;
            }

            $this->swap($index, $parent);
            $index = $parent;
        }
    }

    private function siftDown(int $index): void
    {
        $count = \count($this->heap);

        while (true) {
            $left     = 2 * $index + 1;
            $right    = 2 * $index + 2;
            $smallest = $index;

            if ($left < $count && $this->compare($left, $smallest) < 0) {
                $smallest = $left;
            }

            if ($right < $count && $this->compare($right, $smallest) < 0) {
                $smallest = $right;
            }

            if ($smallest === $index) {
                break;
            }

            $this->swap($index, $smallest);
            $index = $smallest;
        }
    }

    private function compare(int $a, int $b): int
    {
        return ($this->comparator)($this->heap[$a], $this->heap[$b]);
    }

    private function swap(int $a, int $b): void
    {
        [$this->heap[$a], $this->heap[$b]] = [$this->heap[$b], $this->heap[$a]];
    }
}
