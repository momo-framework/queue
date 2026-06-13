<?php

/**
 * Part of Momo Framework.
 *
 * © Momo Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author    Vahe Sargsyan <w33bvGL>
 * @copyright Momo Framework
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
 */

declare(strict_types=1);

namespace Momo\Queue\Tests\Unit;

use InvalidArgumentException;
use Momo\Queue\Backoff\ConstantBackoffPolicy;
use Momo\Queue\Backoff\ExponentialBackoffPolicy;
use Momo\Queue\Backoff\FullJitterBackoffPolicy;
use PHPUnit\Framework\TestCase;

final class BackoffPoliciesTest extends TestCase
{
    public function testConstantPolicyAlwaysReturnsSameDelay(): void
    {
        $policy = new ConstantBackoffPolicy(7);

        self::assertSame(7, $policy->delayFor(2));
        self::assertSame(7, $policy->delayFor(99));
    }

    public function testConstantPolicyRejectsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConstantBackoffPolicy(-1);
    }

    public function testFullJitterStaysWithinCeiling(): void
    {
        $inner  = new ExponentialBackoffPolicy(baseSeconds: 4, maxSeconds: 3600, multiplier: 2.0);
        $policy = new FullJitterBackoffPolicy($inner, static fn (int $max): int => $max);

        self::assertSame(8, $policy->delayFor(3));
    }

    public function testFullJitterUsesRandomizerBetweenZeroAndCeiling(): void
    {
        $inner  = new ConstantBackoffPolicy(100);
        $policy = new FullJitterBackoffPolicy($inner, static fn (int $max): int => \intdiv($max, 2));

        self::assertSame(50, $policy->delayFor(2));
    }

    public function testFullJitterReturnsZeroWhenCeilingIsZero(): void
    {
        $inner  = new ConstantBackoffPolicy(0);
        $policy = new FullJitterBackoffPolicy($inner, static fn (int $max): int => 999);

        self::assertSame(0, $policy->delayFor(2));
    }
}
