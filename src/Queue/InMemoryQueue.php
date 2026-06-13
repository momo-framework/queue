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

use Momo\Queue\Contracts\ClockInterface;
use Momo\Queue\Contracts\JobInterface;
use Momo\Queue\Contracts\QueueInterface;

/**
 * Process-local queue backed by two binary heaps: a ready heap ordered by
 * priority and a delayed heap ordered by availability time.
 *
 * COROUTINE SAFETY: every mutating method completes without an I/O suspension
 * point, so under Swoole's cooperative scheduler push/pop/release are atomic
 * with respect to sibling coroutines in the same worker. State lives for the
 * worker's lifetime and is never shared across workers — use a Redis/database
 * driver for cross-worker fan-out.
 */
final class InMemoryQueue implements QueueInterface
{
    private readonly PriorityQueue $ready;

    private readonly PriorityQueue $delayed;

    /** @var int<0, max> */
    private int $sequence = 0;

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
        $this->ready   = new PriorityQueue(Comparators::byPriority());
        $this->delayed = new PriorityQueue(Comparators::byAvailability());
    }

    public function push(JobInterface $job, int $priority = 0): string
    {
        $id = $this->generateId();

        $this->insert(new Envelope(
            id: $id,
            job: $job,
            priority: $priority,
            availableAt: $this->clock->now(),
            attempts: 0,
            sequence: $this->nextSequence(),
        ));

        return $id;
    }

    public function later(int $delaySeconds, JobInterface $job, int $priority = 0): string
    {
        $id = $this->generateId();

        $this->insert(new Envelope(
            id: $id,
            job: $job,
            priority: $priority,
            availableAt: $this->clock->now() + \max(0, $delaySeconds),
            attempts: 0,
            sequence: $this->nextSequence(),
        ));

        return $id;
    }

    public function pop(): ?Envelope
    {
        $this->promoteDueEnvelopes();

        return $this->ready->extract();
    }

    public function release(Envelope $envelope, int $delaySeconds): void
    {
        $this->insert(
            $envelope
                ->withAvailableAt($this->clock->now() + \max(0, $delaySeconds))
                ->withSequence($this->nextSequence())
        );
    }

    public function size(): int
    {
        return $this->ready->size() + $this->delayed->size();
    }

    private function insert(Envelope $envelope): void
    {
        if ($envelope->isReady($this->clock->now())) {
            $this->ready->insert($envelope);
            return;
        }

        $this->delayed->insert($envelope);
    }

    private function promoteDueEnvelopes(): void
    {
        $now = $this->clock->now();

        while (($top = $this->delayed->peek()) !== null && $top->isReady($now)) {
            $this->delayed->extract();
            $this->ready->insert($top);
        }
    }

    /**
     * @return int<0, max>
     */
    private function nextSequence(): int
    {
        return $this->sequence++;
    }

    /**
     * @return non-empty-string
     */
    private function generateId(): string
    {
        return \bin2hex(\random_bytes(16));
    }
}
