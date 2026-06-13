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

use Momo\Queue\Contracts\BackoffPolicyInterface;

/**
 * Wraps another policy and returns a uniformly random delay in `[0, computed]`
 * ("full jitter", per the AWS Architecture Blog). De-synchronises retries from
 * thousands of jobs that failed at the same instant, preventing a synchronised
 * retry storm against the recovering dependency.
 */
final class FullJitterBackoffPolicy implements BackoffPolicyInterface
{
    /** @var callable(int): int */
    private readonly mixed $randomizer;

    /**
     * @param (callable(int): int)|null $randomizer maps a ceiling to a value in [0, ceiling]; defaults to random_int
     */
    public function __construct(
        private readonly BackoffPolicyInterface $inner,
        ?callable $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? static fn (int $max): int => \random_int(0, \max(0, $max));
    }

    public function delayFor(int $attempt): int
    {
        $ceiling = $this->inner->delayFor($attempt);

        if ($ceiling <= 0) {
            return 0;
        }

        return \max(0, ($this->randomizer)($ceiling));
    }
}
