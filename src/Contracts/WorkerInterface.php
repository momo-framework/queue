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

use Momo\Queue\Worker\WorkerOptions;

/**
 * Long-running consumer that drains a {@see QueueInterface}.
 */
interface WorkerInterface
{
    /**
     * Run the processing loop until a stop condition in $options is met or
     * {@see stop()} is called from a signal handler.
     */
    public function run(WorkerOptions $options): void;

    /**
     * Process at most one ready envelope. Returns true when an envelope was
     * handled, false when the queue had nothing ready. Never blocks waiting for
     * new work — callers poll. Primarily used by tests and `--once`.
     */
    public function runOnce(): bool;

    /**
     * Request a graceful shutdown: the loop exits after the in-flight job, if
     * any, completes. Safe to call from a Swoole signal handler.
     */
    public function stop(): void;
}
