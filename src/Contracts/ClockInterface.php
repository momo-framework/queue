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

namespace Momo\Queue\Contracts;

/**
 * Monotonic-enough wall-clock source. Injected everywhere time is read so the
 * queue's availability and backoff logic is deterministic under test.
 */
interface ClockInterface
{
    /**
     * Current Unix timestamp in whole seconds.
     *
     * @return int<0, max>
     */
    public function now(): int;
}
