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

namespace Momo\Queue\Tests\Unit;

use function intdiv;

use InvalidArgumentException;
use Momo\Queue\Backoff\ConstantBackoffPolicy;
use Momo\Queue\Backoff\ExponentialBackoffPolicy;
use Momo\Queue\Backoff\FullJitterBackoffPolicy;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class BackoffPoliciesTest extends TestCase
{
    public function test_constant_policy_always_returns_same_delay(): void
    {
        $policy = new ConstantBackoffPolicy(7);

        self::assertSame(7, $policy->delayFor(2));
        self::assertSame(7, $policy->delayFor(99));
    }

    public function test_constant_policy_rejects_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConstantBackoffPolicy(-1);
    }

    public function test_full_jitter_stays_within_ceiling(): void
    {
        $inner = new ExponentialBackoffPolicy(baseSeconds: 4, maxSeconds: 3600, multiplier: 2.0);
        $policy = new FullJitterBackoffPolicy($inner, static fn (int $max): int => $max);

        self::assertSame(8, $policy->delayFor(3));
    }

    public function test_full_jitter_uses_randomizer_between_zero_and_ceiling(): void
    {
        $inner = new ConstantBackoffPolicy(100);
        $policy = new FullJitterBackoffPolicy($inner, static fn (int $max): int => intdiv($max, 2));

        self::assertSame(50, $policy->delayFor(2));
    }

    public function test_full_jitter_returns_zero_when_ceiling_is_zero(): void
    {
        $inner = new ConstantBackoffPolicy(0);
        $policy = new FullJitterBackoffPolicy($inner, static fn (int $max): int => 999);

        self::assertSame(0, $policy->delayFor(2));
    }
}
