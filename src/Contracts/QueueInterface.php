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

namespace Momo\Queue\Contracts;

use Momo\Queue\Queue\Envelope;

/**
 * A priority-ordered, delay-aware job queue.
 *
 * Ordering contract for {@see pop()}: among all envelopes whose availability
 * time has passed, the one with the highest priority is returned first; ties
 * are broken by insertion order (FIFO). Envelopes scheduled for the future are
 * never returned early.
 */
interface QueueInterface
{
    /**
     * Enqueue a job for immediate processing.
     *
     * @param int $priority higher runs first; defaults to normal priority
     * @return non-empty-string the generated envelope id
     */
    public function push(JobInterface $job, int $priority = 0): string;

    /**
     * Enqueue a job that only becomes available after the given delay.
     *
     * @param int<0, max> $delaySeconds
     * @param int $priority higher runs first; defaults to normal priority
     * @return non-empty-string the generated envelope id
     */
    public function later(int $delaySeconds, JobInterface $job, int $priority = 0): string;

    /**
     * Remove and return the next ready envelope, or null when none is available.
     */
    public function pop(): ?Envelope;

    /**
     * Re-enqueue an envelope for a later retry without resetting its identity.
     *
     * @param int<0, max> $delaySeconds
     */
    public function release(Envelope $envelope, int $delaySeconds): void;

    /**
     * Number of envelopes currently held, including those not yet available.
     *
     * @return int<0, max>
     */
    public function size(): int;
}
