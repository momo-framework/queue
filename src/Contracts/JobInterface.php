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

use Throwable;

/**
 * A unit of deferred work executed by a queue worker.
 *
 * Implementations MUST be self-contained: a job carries every value it needs
 * to run, never a live service handle, because it may be executed in a
 * different coroutine or worker process than the one that enqueued it.
 */
interface JobInterface
{
    /**
     * Execute the job. Throwing marks the attempt as failed and triggers retry
     * scheduling according to the configured {@see BackoffPolicyInterface}.
     *
     * @throws Throwable when the work cannot be completed
     */
    public function handle(): void;

    /**
     * Called once after the final attempt has been exhausted. Use it to emit a
     * compensating event, alert an operator, or persist a dead-letter record.
     */
    public function failed(Throwable $exception): void;

    /**
     * Maximum number of attempts (initial run + retries) before the job is
     * moved to the failed-job store.
     *
     * @return positive-int
     */
    public function maxAttempts(): int;
}
