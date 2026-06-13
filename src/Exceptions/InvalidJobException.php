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

namespace Momo\Queue\Exceptions;

/**
 * Thrown when a job declares an invalid configuration, e.g. a non-positive
 * maximum attempt count.
 */
final class InvalidJobException extends QueueException
{
    /**
     * @param class-string $jobClass
     */
    public static function nonPositiveMaxAttempts(string $jobClass, int $given): self
    {
        return new self(
            sprintf('Job %s must declare a positive maxAttempts(), got %d.', $jobClass, $given)
        );
    }
}
