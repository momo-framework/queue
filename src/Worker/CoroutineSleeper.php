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

namespace Momo\Queue\Worker;

use function extension_loaded;

use Momo\Queue\Contracts\SleeperInterface;

use function round;
use function usleep;

/**
 * Idle-wait that yields the Swoole coroutine scheduler when the extension is
 * present, so a polling worker never blocks the reactor thread. Outside Swoole
 * (e.g. a plain CLI worker) it falls back to a blocking microsecond sleep.
 *
 * The Swoole call is dispatched dynamically so static analysis stays green on
 * environments without the swoole stubs installed.
 */
final class CoroutineSleeper implements SleeperInterface
{
    public function sleep(float|int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        if (extension_loaded('swoole')) {
            $sleep = \Swoole\Coroutine::sleep(...);
            $sleep((float) $seconds);

            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }
}
