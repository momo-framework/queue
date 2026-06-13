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

use Momo\Queue\Tests\Fixtures\AlwaysFailingJob;
use Momo\Queue\Tests\Fixtures\RecordingJob;
use PHPUnit\Framework\TestCase;

final class AbstractJobTest extends TestCase
{
    public function testDefaultMaxAttemptsWhenNoAttribute(): void
    {
        self::assertSame(3, (new RecordingJob())->maxAttempts());
    }

    public function testMaxAttemptsReadFromAttribute(): void
    {
        self::assertSame(3, (new AlwaysFailingJob())->maxAttempts());
    }

    public function testDefaultPriorityIsZero(): void
    {
        self::assertSame(0, (new RecordingJob())->priority());
    }

    public function testDefaultFailedHandlerDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();

        (new RecordingJob())->failed(new \RuntimeException('ignored'));
    }
}
