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

use function max;

use Momo\Queue\Contracts\BackoffPolicyInterface;

use function random_int;

/**
 * Wraps another policy and returns a uniformly random delay in `[0, computed]`
 * ("full jitter", per the AWS Architecture Blog). De-synchronises retries from
 * thousands of jobs that failed at the same instant, preventing a synchronised
 * retry storm against the recovering dependency.
 */
final readonly class FullJitterBackoffPolicy implements BackoffPolicyInterface
{
    /** @var callable(int): int */
    private mixed $randomizer;

    /**
     * @param null|(callable(int): int) $randomizer maps a ceiling to a value in [0, ceiling]; defaults to random_int
     */
    public function __construct(
        private BackoffPolicyInterface $inner,
        callable|null $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? static fn (int $max): int => random_int(0, max(0, $max));
    }

    public function delayFor(int $attempt): int
    {
        $ceiling = $this->inner->delayFor($attempt);

        if ($ceiling <= 0) {
            return 0;
        }

        return max(0, ($this->randomizer)($ceiling));
    }
}
