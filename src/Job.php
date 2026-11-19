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

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Job
{
    /** @param list<int> $backoff */
    public function __construct(
        public string|null $queue = null,
        public int $attempts = 3,
        public array $backoff = [],
    ) {}

    public function delayBefore(int $next): int
    {
        if ($this->backoff !== []) {
            return $this->backoff[min($next - 2, count($this->backoff) - 1)] ?? 0;
        }

        $cap = min(3600, 1 << max(0, min($next, 12)));

        return random_int(1, $cap);
    }
}
