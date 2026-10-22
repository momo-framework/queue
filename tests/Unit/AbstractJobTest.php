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

use Momo\Queue\Tests\Fixtures\AlwaysFailingJob;
use Momo\Queue\Tests\Fixtures\RecordingJob;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class AbstractJobTest extends TestCase
{
    public function test_default_max_attempts_when_no_attribute(): void
    {
        self::assertSame(3, new RecordingJob()->maxAttempts());
    }

    public function test_max_attempts_read_from_attribute(): void
    {
        self::assertSame(3, new AlwaysFailingJob()->maxAttempts());
    }

    public function test_default_priority_is_zero(): void
    {
        self::assertSame(0, new RecordingJob()->priority());
    }

    public function test_default_failed_handler_does_not_throw(): void
    {
        $this->expectNotToPerformAssertions();

        new RecordingJob()->failed(new RuntimeException('ignored'));
    }
}
