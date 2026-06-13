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

namespace Momo\Queue\Worker;

/**
 * Immutable run configuration for {@see Worker::run()}.
 */
final readonly class WorkerOptions
{
    /**
     * @param int<0, max> $maxJobs        process at most this many jobs then exit; 0 = unlimited
     * @param int<0, max> $sleepSeconds   idle wait when the queue has nothing ready
     * @param bool        $stopWhenEmpty  exit as soon as the queue drains instead of polling
     * @param int<0, max> $memoryLimitMb  exit when RSS exceeds this, letting the guardian respawn a clean worker; 0 = no limit
     */
    public function __construct(
        public int $maxJobs = 0,
        public int $sleepSeconds = 1,
        public bool $stopWhenEmpty = false,
        public int $memoryLimitMb = 256,
    ) {
    }

    /**
     * Drain the queue once and exit — the shape tests and `queue:work --once` use.
     */
    public static function once(): self
    {
        return new self(maxJobs: 1, stopWhenEmpty: true);
    }
}
