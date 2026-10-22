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
