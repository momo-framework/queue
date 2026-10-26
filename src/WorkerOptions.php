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

namespace Momo\Queue;

final readonly class WorkerOptions
{
    /**
     * @param list<string> $queues        polled in this order, the first non-empty wins
     * @param int          $concurrency   jobs run at once, one coroutine each; 1 without Swoole
     * @param int          $maxJobs       stop after this many jobs, 0 for never
     * @param bool         $stopWhenEmpty stop when every queue is empty
     * @param int          $visibility    seconds a taken job stays hidden before another worker may take it
     * @param float        $idle          seconds to wait when every queue is empty
     * @param int          $memoryMb      stop when the process uses more, so the supervisor starts a fresh one
     */
    public function __construct(
        public array $queues = ['default'],
        public int $concurrency = 1,
        public int $maxJobs = 0,
        public bool $stopWhenEmpty = false,
        public int $visibility = 90,
        public float $idle = 1.0,
        public int $memoryMb = 256,
    ) {}
}
