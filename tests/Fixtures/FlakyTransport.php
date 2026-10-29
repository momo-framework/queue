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

namespace Momo\Queue\Tests\Fixtures;

use Momo\Queue\Contracts\Transport;
use Momo\Queue\Envelope;
use RuntimeException;
use Throwable;

/**
 * A transport that loses the connection on demand: the next pop() or ack()
 * throws once, as a broker restart does.
 */
final class FlakyTransport implements Transport
{
    public int $popFailures = 0;

    public int $ackFailures = 0;

    public function __construct(private readonly Transport $inner) {}

    public function push(Envelope $envelope, int $delay = 0): void
    {
        $this->inner->push($envelope, $delay);
    }

    public function pop(string $queue, int $visibility): Envelope|null
    {
        if ($this->popFailures > 0) {
            $this->popFailures--;

            throw new RuntimeException('connection refused');
        }

        return $this->inner->pop($queue, $visibility);
    }

    public function ack(Envelope $envelope): void
    {
        if ($this->ackFailures > 0) {
            $this->ackFailures--;

            throw new RuntimeException('connection reset');
        }

        $this->inner->ack($envelope);
    }

    public function release(Envelope $envelope, int $delay): void
    {
        $this->inner->release($envelope, $delay);
    }

    public function fail(Envelope $envelope, Throwable $error): void
    {
        $this->inner->fail($envelope, $error);
    }

    public function size(string $queue): int
    {
        return $this->inner->size($queue);
    }

    public function failed(): array
    {
        return $this->inner->failed();
    }

    public function retry(string $id): bool
    {
        return $this->inner->retry($id);
    }

    public function forget(string $id): bool
    {
        return $this->inner->forget($id);
    }
}
