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

use Momo\Queue\FailedJob\InMemoryFailedJobStore;
use Momo\Queue\Queue\Envelope;
use Momo\Queue\Support\MutableClock;
use Momo\Queue\Tests\Fixtures\RecordingJob;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FailedJobStoreTest extends TestCase
{
    public function testStoreAndRetrieveMostRecentFirst(): void
    {
        $store = new InMemoryFailedJobStore(new MutableClock(1_000));

        $store->store($this->envelope('a'), new RuntimeException('first'));
        $store->store($this->envelope('b'), new RuntimeException('second'));

        $records = $store->all();

        self::assertCount(2, $records);
        self::assertSame('second', $records[0]->message);
        self::assertSame('first', $records[1]->message);
        self::assertSame(1_000, $records[0]->failedAt);
    }

    public function testForgetRemovesRecord(): void
    {
        $store = new InMemoryFailedJobStore(new MutableClock(0));
        $id    = $store->store($this->envelope('a'), new RuntimeException('x'));

        self::assertTrue($store->forget($id));
        self::assertSame([], $store->all());
    }

    public function testForgetUnknownIdReturnsFalse(): void
    {
        $store = new InMemoryFailedJobStore(new MutableClock(0));

        self::assertFalse($store->forget('does-not-exist'));
    }

    public function testFlushReturnsRemovedCount(): void
    {
        $store = new InMemoryFailedJobStore(new MutableClock(0));
        $store->store($this->envelope('a'), new RuntimeException('x'));
        $store->store($this->envelope('b'), new RuntimeException('y'));

        self::assertSame(2, $store->flush());
        self::assertSame([], $store->all());
    }

    /**
     * @param non-empty-string $id
     */
    private function envelope(string $id): Envelope
    {
        return new Envelope(
            id: $id,
            job: new RecordingJob(),
            priority: 0,
            availableAt: 0,
            attempts: 1,
            sequence: 0,
        );
    }
}
