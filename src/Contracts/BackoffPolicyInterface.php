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
 * Computes the delay, in whole seconds, before a failed job is retried.
 */
interface BackoffPolicyInterface
{
    /**
     * Delay in seconds before the given attempt should run.
     *
     * @param positive-int $attempt the attempt number about to be scheduled (2 = first retry)
     * @return int<0, max>
     */
    public function delayFor(int $attempt): int;
}
