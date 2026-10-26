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

use Momo\Database\DatabaseManager;
use Momo\Database\Migrations\Migrator;
use Momo\Database\Migrations\SqlTarget;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Envelope;
use Momo\Queue\Tests\Fixtures\Billing\Application\SendInvoice;
use Momo\Queue\Transport\DatabaseTransport;
use Momo\Queue\Transport\RedisTransport;
use Momo\Redis\RedisManager;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * One contract, every durable transport: SQLite always, Redis when
 * REDIS_HOST is set.
 *
 * @internal
 */
#[CoversClass(DatabaseTransport::class)]
#[CoversClass(RedisTransport::class)]
final class TransportTest extends TestCase
{
    #[Test]
    #[DataProvider('transports')]
    public function push_pop_ack_release_fail_and_retry(string $kind): void
    {
        $t = $this->transport($kind);
        $t->push(Envelope::wrap(new SendInvoice(1), 'mail'));
        $t->push(Envelope::wrap(new SendInvoice(2), 'mail'), 3600);

        self::assertSame(2, $t->size('mail'));

        $first = $t->pop('mail', 60);
        self::assertInstanceOf(Envelope::class, $first);
        self::assertSame(1, $first->attempts);
        self::assertEquals(new SendInvoice(1), $first->job());
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function transports(): iterable
    {
        yield 'database' => ['database'];

        yield 'redis' => ['redis'];
    }

    private function transport(string $kind): Transport
    {
        if ($kind === 'redis') {
            $host = getenv('REDIS_HOST');
            if (! is_string($host) || ! extension_loaded('redis')) {
                self::markTestSkipped('Set REDIS_HOST and install ext-redis to run against Redis.');
            }

            $redis = new RedisManager(['default' => ['host' => $host]])->connection();
            $prefix = 'queue-test-' . bin2hex(random_bytes(3)) . ':';

            return new RedisTransport($redis, $prefix);
        }

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is not installed.');
        }

        $manager = new DatabaseManager(['default' => 's', 'connections' => ['s' => ['driver' => 'sqlite', 'database' => ':memory:']]]);
        $migrator = new Migrator($manager->connection(), 'migrations', [new SqlTarget($manager)]);
        $migrator->addPath(dirname(__DIR__, 2) . '/database/migrations');
        $migrator->run();

        return new DatabaseTransport($manager->connection());
    }
}
