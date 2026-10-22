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
 * Abstraction over the worker idle-wait so the loop never calls a blocking
 * sleep directly. The Swoole implementation yields the coroutine; tests inject
 * a no-op so the loop spins deterministically.
 */
interface SleeperInterface
{
    /**
     * Pause the current execution context for the given number of seconds.
     *
     * @param float|int<0, max> $seconds
     */
    public function sleep(float|int $seconds): void;
}
