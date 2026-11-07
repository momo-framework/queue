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
    /** @param list<string> $queues */
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
