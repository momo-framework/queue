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
