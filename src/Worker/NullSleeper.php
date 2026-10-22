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

use Momo\Queue\Contracts\SleeperInterface;

/**
 * No-op sleeper. Lets the worker loop spin deterministically under test without
 * wall-clock delays.
 */
final class NullSleeper implements SleeperInterface
{
    public function sleep(float|int $seconds): void {}
}
