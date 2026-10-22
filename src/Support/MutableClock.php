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

namespace Momo\Queue\Support;

use Momo\Queue\Contracts\ClockInterface;

/**
 * Hand-advanced clock for deterministic tests of delay and backoff behaviour.
 */
final class MutableClock implements ClockInterface
{
    /**
     * @param int<0, max> $current
     */
    public function __construct(private int $current = 0) {}

    public function now(): int
    {
        return $this->current;
    }

    /**
     * Advance the clock forward by the given number of seconds.
     *
     * @param int<0, max> $seconds
     */
    public function advance(int $seconds): void
    {
        $this->current += $seconds;
    }
}
