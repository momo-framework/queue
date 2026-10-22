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

use function time;

/**
 * Wall-clock backed by the system time.
 */
final class SystemClock implements ClockInterface
{
    public function now(): int
    {
        return time();
    }
}
