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

namespace Momo\Queue\Transport;

use Momo\Queue\Contracts\Transport;
use Momo\Queue\Envelope;
use Momo\Queue\Exceptions\QueueException;
use Throwable;

/**
 * Stands in for a transport QUEUE_CONNECTION names but no installed
 * package provides. Every call says which package to install: pushing a
 * job must never quietly run it inline or drop it.
 */
final readonly class MissingTransport implements Transport
{
    public function __construct(private string $name) {}

    public function push(Envelope $envelope, int $delay = 0): void
    {
        throw $this->missing();
    }

    public function pop(string $queue, int $visibility): Envelope|null
    {
        throw $this->missing();
    }

    public function ack(Envelope $envelope): void
    {
        throw $this->missing();
    }

    public function release(Envelope $envelope, int $delay): void
    {
        throw $this->missing();
    }

    public function fail(Envelope $envelope, Throwable $error): void
    {
        throw $this->missing();
    }

    public function size(string $queue): int
    {
        throw $this->missing();
    }

    public function failed(): array
    {
        throw $this->missing();
    }

    public function retry(string $id): bool
    {
        throw $this->missing();
    }

    public function forget(string $id): bool
    {
        throw $this->missing();
    }

    private function missing(): QueueException
    {
        return new QueueException(sprintf(
            'QUEUE_CONNECTION is "%s", and no installed package provides it. Built in: sync, memory, redis, database; amqp comes with anidzen/momo-amqp.',
            $this->name,
        ));
    }
}
