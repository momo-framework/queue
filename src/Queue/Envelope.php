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

use Momo\Queue\Contracts\JobInterface;

/**
 * Immutable transport record wrapping a {@see JobInterface} with the metadata
 * the queue and worker need: identity, scheduling priority, availability time,
 * retry count and a monotonic sequence used as a FIFO tie-breaker.
 */
final readonly class Envelope
{
    /**
     * @param non-empty-string $id
     * @param int<0, max>      $availableAt unix timestamp from which the job may run
     * @param int<0, max>      $attempts    completed attempts so far (0 = never run)
     * @param int<0, max>      $sequence    insertion order, assigned by the queue
     */
    public function __construct(
        public string $id,
        public JobInterface $job,
        public int $priority,
        public int $availableAt,
        public int $attempts,
        public int $sequence,
    ) {
    }

    /**
     * Whether the envelope is eligible to run at the given timestamp.
     *
     * @param int<0, max> $now
     */
    public function isReady(int $now): bool
    {
        return $this->availableAt <= $now;
    }

    /**
     * Copy with a different completed-attempt count.
     *
     * @param int<0, max> $attempts
     */
    public function withAttempts(int $attempts): self
    {
        return new self($this->id, $this->job, $this->priority, $this->availableAt, $attempts, $this->sequence);
    }

    /**
     * Copy with a different availability timestamp.
     *
     * @param int<0, max> $availableAt
     */
    public function withAvailableAt(int $availableAt): self
    {
        return new self($this->id, $this->job, $this->priority, $availableAt, $this->attempts, $this->sequence);
    }

    /**
     * Copy with a freshly assigned queue sequence.
     *
     * @param int<0, max> $sequence
     */
    public function withSequence(int $sequence): self
    {
        return new self($this->id, $this->job, $this->priority, $this->availableAt, $this->attempts, $sequence);
    }
}
