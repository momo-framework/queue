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
use Momo\Queue\FailedJob;
use Momo\Redis\Connection;
use Throwable;

/**
 * A reliable queue on Redis. Each queue is a list of ready jobs, a sorted
 * set of delayed ones and a sorted set of reserved ones scored by their
 * deadline. Every step is one Lua script, so two workers can never take
 * the same job, and a job whose worker died returns to the list when its
 * deadline passes.
 */
final readonly class RedisTransport implements Transport
{
    /**
     * Moves due delayed and expired reserved jobs to the list, takes the
     * first one and reserves it until now + visibility.
     */
    private const string POP = <<<'LUA'
        local function migrate(from, to, now)
            local due = redis.call('ZRANGEBYSCORE', from, '-inf', now, 'LIMIT', 0, 100)
            if #due > 0 then
                redis.call('ZREM', from, unpack(due))
                for i = 1, #due do redis.call('RPUSH', to, due[i]) end
            end
        end
        migrate(KEYS[2], KEYS[1], ARGV[1])
        migrate(KEYS[3], KEYS[1], ARGV[1])
        local job = redis.call('LPOP', KEYS[1])
        if not job then return false end
        local decoded = cjson.decode(job)
        decoded['attempts'] = decoded['attempts'] + 1
        local reserved = cjson.encode(decoded)
        redis.call('ZADD', KEYS[3], ARGV[1] + ARGV[2], reserved)
        return reserved
        LUA;

    /** Takes the job out of the reserved set and schedules it again. */
    private const string RELEASE = <<<'LUA'
        if redis.call('ZREM', KEYS[1], ARGV[1]) == 1 then
            redis.call('ZADD', KEYS[2], ARGV[3], ARGV[2])
        end
        return 1
        LUA;

    public function __construct(
        private Connection $redis,
        private string $prefix = 'queue:',
    ) {}

    public function push(Envelope $envelope, int $delay = 0): void
    {
        $delay > 0
            ? $this->redis->zAdd($this->key($envelope->queue, 'delayed'), time() + $delay, $envelope->toJson())
            : $this->redis->rPush($this->key($envelope->queue), $envelope->toJson());
    }

    public function pop(string $queue, int $visibility): Envelope|null
    {
        $raw = $this->redis->eval(self::POP, [$this->key($queue), $this->key($queue, 'delayed'), $this->key($queue, 'reserved'), (string) time(), (string) $visibility], 3);

        return is_string($raw) ? Envelope::fromJson($raw) : null;
    }

    public function ack(Envelope $envelope): void
    {
        $this->redis->zRem($this->key($envelope->queue, 'reserved'), $envelope->reservation);
    }

    public function release(Envelope $envelope, int $delay): void
    {
        $next = new Envelope($envelope->id, $envelope->queue, $envelope->class, $envelope->payload, $envelope->attempts, $envelope->pushedAt);
        $this->redis->eval(self::RELEASE, [$this->key($envelope->queue, 'reserved'), $this->key($envelope->queue, 'delayed'), $envelope->reservation, $next->toJson(), (string) (time() + $delay)], 2);
    }

    public function fail(Envelope $envelope, Throwable $error): void
    {
        $this->ack($envelope);
        $this->redis->hSet($this->prefix . 'failed', $envelope->id, json_encode([
            'envelope' => $envelope->toJson(),
            'error' => $error::class . ': ' . $error->getMessage(),
            'failed_at' => time(),
        ], JSON_THROW_ON_ERROR));
    }

    public function size(string $queue): int
    {
        $ready = $this->redis->lLen($this->key($queue));
        $delayed = $this->redis->zCard($this->key($queue, 'delayed'));

        return (is_int($ready) ? $ready : 0) + (is_int($delayed) ? $delayed : 0);
    }

    public function failed(): array
    {
        $all = $this->redis->hGetAll($this->prefix . 'failed');
        $out = [];
        foreach (is_array($all) ? $all : [] as $raw) {
            $failed = is_string($raw) ? $this->decodeFailed($raw) : null;
            if ($failed instanceof FailedJob) {
                $out[] = $failed;
            }
        }

        usort($out, static fn (FailedJob $a, FailedJob $b): int => $b->failedAt <=> $a->failedAt);

        return $out;
    }

    public function retry(string $id): bool
    {
        $raw = $this->redis->hGet($this->prefix . 'failed', $id);
        $failed = is_string($raw) ? $this->decodeFailed($raw) : null;
        if (! $failed instanceof FailedJob) {
            return false;
        }

        $e = $failed->envelope;
        $this->push(new Envelope($e->id, $e->queue, $e->class, $e->payload, 0, $e->pushedAt));
        $this->redis->hDel($this->prefix . 'failed', $id);

        return true;
    }

    public function forget(string $id): bool
    {
        $deleted = $this->redis->hDel($this->prefix . 'failed', $id);

        return is_int($deleted) && $deleted > 0;
    }

    private function decodeFailed(string $raw): FailedJob|null
    {
        $d = json_decode($raw, true);
        if (! is_array($d) || ! is_string($d['envelope'] ?? null)) {
            return null;
        }

        return new FailedJob(Envelope::fromJson($d['envelope']), is_string($d['error'] ?? null) ? $d['error'] : '', is_int($d['failed_at'] ?? null) ? $d['failed_at'] : 0);
    }

    private function key(string $queue, string $part = ''): string
    {
        return $this->prefix . $queue . ($part === '' ? '' : ':' . $part);
    }
}
