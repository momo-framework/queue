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
 * No-op sleeper. Lets the worker loop spin deterministically under test without
 * wall-clock delays.
 */
final class NullSleeper implements SleeperInterface
{
    public function sleep(int|float $seconds): void
    {
    }
}
