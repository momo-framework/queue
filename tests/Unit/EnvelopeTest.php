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

use Momo\Queue\Queue\Envelope;
use Momo\Queue\Tests\Fixtures\RecordingJob;
use PHPUnit\Framework\TestCase;

final class EnvelopeTest extends TestCase
{
    public function testIsReadyComparesAgainstAvailability(): void
    {
        $envelope = $this->envelope(availableAt: 100);

        self::assertFalse($envelope->isReady(99));
        self::assertTrue($envelope->isReady(100));
        self::assertTrue($envelope->isReady(101));
    }

    public function testWithAttemptsProducesNewImmutableCopy(): void
    {
        $envelope = $this->envelope(availableAt: 0);
        $updated  = $envelope->withAttempts(5);

        self::assertSame(0, $envelope->attempts);
        self::assertSame(5, $updated->attempts);
        self::assertSame($envelope->id, $updated->id);
    }

    public function testWithAvailableAtAndSequenceCopy(): void
    {
        $envelope = $this->envelope(availableAt: 0);

        self::assertSame(42, $envelope->withAvailableAt(42)->availableAt);
        self::assertSame(7, $envelope->withSequence(7)->sequence);
    }

    /**
     * @param int<0, max> $availableAt
     */
    private function envelope(int $availableAt): Envelope
    {
        return new Envelope(
            id: 'id',
            job: new RecordingJob(),
            priority: 0,
            availableAt: $availableAt,
            attempts: 0,
            sequence: 0,
        );
    }
}
