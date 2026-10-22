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

namespace Momo\Queue\Backoff;

use InvalidArgumentException;

use function max;

use Momo\Queue\Contracts\BackoffPolicyInterface;

/**
 * Fixed retry delay regardless of attempt number. Useful for jobs whose
 * downstream recovers on a predictable cadence.
 */
final readonly class ConstantBackoffPolicy implements BackoffPolicyInterface
{
    /**
     * @param int $seconds fixed retry delay; must be zero or greater
     *
     * @throws InvalidArgumentException when the delay is negative
     */
    public function __construct(private int $seconds = 5)
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('seconds must be zero or greater.');
        }
    }

    public function delayFor(int $attempt): int
    {
        return max(0, $this->seconds);
    }
}
