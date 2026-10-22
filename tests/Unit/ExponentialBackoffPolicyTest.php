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

use InvalidArgumentException;
use Momo\Queue\Backoff\ExponentialBackoffPolicy;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ExponentialBackoffPolicyTest extends TestCase
{
    public function test_first_retry_waits_base_seconds(): void
    {
        $policy = new ExponentialBackoffPolicy(baseSeconds: 5, maxSeconds: 3600, multiplier: 2.0);

        self::assertSame(5, $policy->delayFor(2));
    }

    public function test_delay_grows_geometrically(): void
    {
        $policy = new ExponentialBackoffPolicy(baseSeconds: 5, maxSeconds: 3600, multiplier: 2.0);

        self::assertSame(5, $policy->delayFor(2));
        self::assertSame(10, $policy->delayFor(3));
        self::assertSame(20, $policy->delayFor(4));
        self::assertSame(40, $policy->delayFor(5));
    }

    public function test_delay_is_capped_at_max_seconds(): void
    {
        $policy = new ExponentialBackoffPolicy(baseSeconds: 5, maxSeconds: 30, multiplier: 2.0);

        self::assertSame(30, $policy->delayFor(10));
    }

    public function test_attempts_before_first_retry_use_base(): void
    {
        $policy = new ExponentialBackoffPolicy(baseSeconds: 5, maxSeconds: 3600, multiplier: 2.0);

        self::assertSame(5, $policy->delayFor(1));
    }

    public function test_rejects_non_positive_base(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExponentialBackoffPolicy(baseSeconds: 0);
    }

    public function test_rejects_max_below_base(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExponentialBackoffPolicy(baseSeconds: 10, maxSeconds: 5);
    }

    public function test_rejects_multiplier_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExponentialBackoffPolicy(multiplier: 0.5);
    }
}
