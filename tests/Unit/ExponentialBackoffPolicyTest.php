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
use Momo\Queue\Backoff\ExponentialBackoffPolicy;
use PHPUnit\Framework\TestCase;

final class ExponentialBackoffPolicyTest extends TestCase
{
    public function testFirstRetryWaitsBaseSeconds(): void
    {
        $policy = new ExponentialBackoffPolicy(baseSeconds: 5, maxSeconds: 3600, multiplier: 2.0);

        self::assertSame(5, $policy->delayFor(2));
    }

    public function testDelayGrowsGeometrically(): void
    {
        $policy = new ExponentialBackoffPolicy(baseSeconds: 5, maxSeconds: 3600, multiplier: 2.0);

        self::assertSame(5, $policy->delayFor(2));
        self::assertSame(10, $policy->delayFor(3));
        self::assertSame(20, $policy->delayFor(4));
        self::assertSame(40, $policy->delayFor(5));
    }

    public function testDelayIsCappedAtMaxSeconds(): void
    {
        $policy = new ExponentialBackoffPolicy(baseSeconds: 5, maxSeconds: 30, multiplier: 2.0);

        self::assertSame(30, $policy->delayFor(10));
    }

    public function testAttemptsBeforeFirstRetryUseBase(): void
    {
        $policy = new ExponentialBackoffPolicy(baseSeconds: 5, maxSeconds: 3600, multiplier: 2.0);

        self::assertSame(5, $policy->delayFor(1));
    }

    public function testRejectsNonPositiveBase(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExponentialBackoffPolicy(baseSeconds: 0);
    }

    public function testRejectsMaxBelowBase(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExponentialBackoffPolicy(baseSeconds: 10, maxSeconds: 5);
    }

    public function testRejectsMultiplierBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExponentialBackoffPolicy(multiplier: 0.5);
    }
}
