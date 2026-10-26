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

use Momo\Database\ConnectionInterface;
use Momo\Database\Grammar;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Envelope;
use Momo\Queue\FailedJob;
use Throwable;

/**
 * Jobs in a table of the app's database. A job dispatched inside a
 * transaction is committed or rolled back with the data it belongs to,
 * the transactional outbox without a relay. Workers take jobs with
 * SELECT ... FOR UPDATE SKIP LOCKED, so they never wait for each other.
 */
final readonly class DatabaseTransport implements Transport
{
    public function __construct(
        private ConnectionInterface $db,
        private string $table = 'jobs',
        private string $failedTable = 'failed_jobs',
    ) {}

    public function push(Envelope $envelope, int $delay = 0): void
    {
        $this->db->table($this->table)->insert([
            'id' => $envelope->id,
            'queue' => $envelope->queue,
            'payload' => $envelope->toJson(),
            'attempts' => $envelope->attempts,
            'available_at' => time() + $delay,
            'reserved_until' => null,
        ]);
    }

    public function pop(string $queue, int $visibility): Envelope|null
    {
        return $this->db->transaction(function (ConnectionInterface $tx) use ($queue, $visibility): Envelope|null {
            $now = time();
            $g = $tx->grammar();
            $row = $tx->selectOne(
                sprintf(
                    'SELECT %s, %s, %s FROM %s WHERE %s = ? AND %s <= ? AND (%s IS NULL OR %s <= ?) ORDER BY %s, %s LIMIT 1%s',
                    $g->wrap('id'),
                    $g->wrap('payload'),
                    $g->wrap('attempts'),
                    $g->wrap($this->table),
                    $g->wrap('queue'),
                    $g->wrap('available_at'),
                    $g->wrap('reserved_until'),
                    $g->wrap('reserved_until'),
                    $g->wrap('available_at'),
                    $g->wrap('id'),
                    $g === Grammar::Sqlite ? '' : ' FOR UPDATE SKIP LOCKED',
                ),
                [$queue, $now, $now],
            );

            if ($row === null || ! is_string($row['id'] ?? null) || ! is_string($row['payload'] ?? null)) {
                return null;
            }

            $attempts = (is_numeric($row['attempts'] ?? null) ? (int) $row['attempts'] : 0) + 1;
            $tx->table($this->table)->where('id', $row['id'])->update(['reserved_until' => $now + $visibility, 'attempts' => $attempts]);

            $e = Envelope::fromJson($row['payload']);

            return new Envelope($e->id, $e->queue, $e->class, $e->payload, $attempts, $e->pushedAt, $e->id);
        });
    }

    public function ack(Envelope $envelope): void
    {
        $this->db->table($this->table)->where('id', $envelope->id)->delete();
    }

    public function release(Envelope $envelope, int $delay): void
    {
        $this->db->table($this->table)->where('id', $envelope->id)->update(['available_at' => time() + $delay, 'reserved_until' => null]);
    }

    public function fail(Envelope $envelope, Throwable $error): void
    {
        $this->db->transaction(function (ConnectionInterface $tx) use ($envelope, $error): void {
            $tx->table($this->table)->where('id', $envelope->id)->delete();
            $tx->table($this->failedTable)->insert([
                'id' => $envelope->id,
                'queue' => $envelope->queue,
                'payload' => $envelope->toJson(),
                'error' => $error::class . ': ' . $error->getMessage(),
                'failed_at' => time(),
            ]);
        });
    }

    public function size(string $queue): int
    {
        return $this->db->table($this->table)->where('queue', $queue)->count();
    }

    public function failed(): array
    {
        return array_map(
            static fn (array $row): FailedJob => new FailedJob(
                Envelope::fromJson(is_string($row['payload'] ?? null) ? $row['payload'] : '{}'),
                is_string($row['error'] ?? null) ? $row['error'] : '',
                is_numeric($row['failed_at'] ?? null) ? (int) $row['failed_at'] : 0,
            ),
            $this->db->table($this->failedTable)->orderBy('failed_at', 'desc')->get(),
        );
    }

    public function retry(string $id): bool
    {
        return $this->db->transaction(function (ConnectionInterface $tx) use ($id): bool {
            $row = $tx->table($this->failedTable)->where('id', $id)->first();
            if ($row === null || ! is_string($row['payload'] ?? null)) {
                return false;
            }

            $e = Envelope::fromJson($row['payload']);
            $tx->table($this->failedTable)->where('id', $id)->delete();
            $tx->table($this->table)->insert([
                'id' => $e->id,
                'queue' => $e->queue,
                'payload' => new Envelope($e->id, $e->queue, $e->class, $e->payload, 0, $e->pushedAt)->toJson(),
                'attempts' => 0,
                'available_at' => time(),
                'reserved_until' => null,
            ]);

            return true;
        });
    }

    public function forget(string $id): bool
    {
        return $this->db->table($this->failedTable)->where('id', $id)->delete() > 0;
    }
}
