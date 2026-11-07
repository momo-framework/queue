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

use Closure;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Envelope;
use Momo\Queue\FailedJob;
use Throwable;

final class MemoryTransport implements Transport
{
    /** @var array<string, list<array{int, Envelope}>> */
    private array $ready = [];

    /** @var array<string, array{int, Envelope}> */
    private array $reserved = [];

    /** @var array<string, FailedJob> */
    private array $failures = [];

    /** @var Closure(): int */
    private readonly Closure $now;

    public function __construct(Closure|null $now = null)
    {
        $this->now = $now ?? time(...);
    }

    public function push(Envelope $envelope, int $delay = 0): void
    {
        $this->ready[$envelope->queue][] = [$this->now() + $delay, $envelope];
    }

    public function pop(string $queue, int $visibility): Envelope|null
    {
        $now = $this->now();
        foreach ($this->reserved as $id => [$deadline, $envelope]) {
            if ($deadline <= $now && $envelope->queue === $queue) {
                unset($this->reserved[$id]);
                $this->ready[$queue][] = [$now, $envelope];
            }
        }

        foreach ($this->ready[$queue] ?? [] as $i => [$at, $envelope]) {
            if ($at <= $now) {
                $envelope = $envelope->attempted();
                $rest = $this->ready[$queue];
                unset($rest[$i]);
                $this->ready[$queue] = array_values($rest);
                $this->reserved[$envelope->id] = [$now + $visibility, $envelope];

                return $envelope;
            }
        }

        return null;
    }

    public function ack(Envelope $envelope): void
    {
        unset($this->reserved[$envelope->id]);
    }

    public function release(Envelope $envelope, int $delay): void
    {
        unset($this->reserved[$envelope->id]);
        $this->push($envelope, $delay);
    }

    public function fail(Envelope $envelope, Throwable $error): void
    {
        unset($this->reserved[$envelope->id]);
        $this->failures[$envelope->id] = new FailedJob($envelope, $error::class . ': ' . $error->getMessage(), $this->now());
    }

    public function size(string $queue): int
    {
        return count($this->ready[$queue] ?? []);
    }

    public function failed(): array
    {
        return array_values($this->failures);
    }

    public function retry(string $id): bool
    {
        $failed = $this->failures[$id] ?? null;
        if ($failed === null) {
            return false;
        }

        unset($this->failures[$id]);
        $e = $failed->envelope;
        $this->push(new Envelope($e->id, $e->queue, $e->class, $e->payload, 0, $e->pushedAt));

        return true;
    }

    public function forget(string $id): bool
    {
        $known = isset($this->failures[$id]);
        unset($this->failures[$id]);

        return $known;
    }

    private function now(): int
    {
        $now = ($this->now)();

        return is_int($now) ? $now : time();
    }
}
