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
 * Computes the delay, in whole seconds, before a failed job is retried.
 */
interface BackoffPolicyInterface
{
    /**
     * Delay in seconds before the given attempt should run.
     *
     * @param positive-int $attempt the attempt number about to be scheduled (2 = first retry)
     *
     * @return int<0, max>
     */
    public function delayFor(int $attempt): int;
}
