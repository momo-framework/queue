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

namespace Momo\Queue\Backoff;

use InvalidArgumentException;
use Momo\Queue\Contracts\BackoffPolicyInterface;

/**
 * Retry delay that grows geometrically with each attempt:
 * `base * multiplier^(attempt - 2)`, clamped to a ceiling.
 *
 * The first retry (attempt 2) waits exactly `base` seconds; every subsequent
 * retry multiplies the previous delay, never exceeding `maxSeconds`. This
 * spreads retry pressure away from a struggling downstream dependency instead
 * of hammering it on a fixed cadence.
 */
final readonly class ExponentialBackoffPolicy implements BackoffPolicyInterface
{
    /**
     * @param int   $baseSeconds delay applied to the first retry; must be >= 1
     * @param int   $maxSeconds  ceiling no delay may exceed; must be >= base
     * @param float $multiplier  growth factor per retry; must be >= 1.0
     *
     * @throws InvalidArgumentException when arguments are out of range
     */
    public function __construct(
        private int $baseSeconds = 5,
        private int $maxSeconds = 3600,
        private float $multiplier = 2.0,
    ) {
        if ($baseSeconds < 1) {
            throw new InvalidArgumentException('baseSeconds must be a positive integer.');
        }

        if ($maxSeconds < $baseSeconds) {
            throw new InvalidArgumentException('maxSeconds must be greater than or equal to baseSeconds.');
        }

        if ($multiplier < 1.0) {
            throw new InvalidArgumentException('multiplier must be greater than or equal to 1.0.');
        }
    }

    public function delayFor(int $attempt): int
    {
        $exponent = \max(0, $attempt - 2);
        $delay    = (float) $this->baseSeconds * ($this->multiplier ** $exponent);

        if ($delay >= (float) $this->maxSeconds) {
            return \max(0, $this->maxSeconds);
        }

        return \max(0, (int) \floor($delay));
    }
}
