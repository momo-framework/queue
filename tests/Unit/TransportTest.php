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

use Momo\Queue\Contracts\Transport;
use Momo\Queue\Envelope;
use Momo\Queue\Tests\Fixtures\Billing\Application\SendInvoice;
use Momo\Queue\Tests\Fixtures\Keys;
use Momo\Queue\Transport\MemoryTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @internal */
#[CoversClass(MemoryTransport::class)]
final class TransportTest extends TestCase
{
    #[Test]
    #[DataProvider('transports')]
    public function push_pop_ack_release_fail_and_retry(string $kind): void
    {
        $t = $this->transport();
        $t->push(Envelope::wrap(new SendInvoice(1), 'mail', Keys::signer()));
        $t->push(Envelope::wrap(new SendInvoice(2), 'mail', Keys::signer()), 3600);

        self::assertSame(2, $t->size('mail'));

        $first = $t->pop('mail', 60);
        self::assertInstanceOf(Envelope::class, $first);
        self::assertSame(1, $first->attempts);
        self::assertEquals(new SendInvoice(1), $first->job(Keys::signer()));
        self::assertNull($t->pop('mail', 60), 'the delayed job is not due and the first is reserved');

        $t->release($first, 0);
        $again = $t->pop('mail', 60);
        self::assertSame(2, $again?->attempts);

        $t->fail($again, new RuntimeException('down'));
        self::assertCount(1, $t->failed());
        self::assertTrue($t->retry($again->id));
        self::assertSame(1, $t->pop('mail', 60)?->attempts);
        self::assertFalse($t->forget('missing'));
    }

    /** @return iterable<string, array{string}> */
    public static function transports(): iterable
    {
        yield 'memory' => ['memory'];
    }

    private function transport(): Transport
    {
        return new MemoryTransport();
    }
}
