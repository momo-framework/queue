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

use Momo\Queue\Contracts\SleeperInterface;

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
    public function sleep(int|float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        if (\extension_loaded('swoole')) {
            $sleep = ['Swoole\\Coroutine', 'sleep'];
            $sleep((float) $seconds);

            return;
        }

        \usleep((int) \round($seconds * 1_000_000));
    }
}
