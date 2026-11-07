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

namespace Momo\Queue;

use Momo\Queue\Exceptions\QueueException;

final readonly class Envelope
{
    public function __construct(
        public string $id,
        public string $queue,
        public string $class,
        public string $payload,
        public int $attempts = 0,
        public int $pushedAt = 0,
        public string $reservation = '',
    ) {}

    public static function wrap(object $job, string $queue): self
    {
        return new self(bin2hex(random_bytes(12)), $queue, $job::class, serialize($job), 0, time());
    }

    public static function fromJson(string $json): self
    {
        $d = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($d)) {
            throw new QueueException('A queued job is not a JSON object.');
        }

        return new self(
            self::str($d['id'] ?? null),
            self::str($d['queue'] ?? null),
            self::str($d['class'] ?? null),
            (string) base64_decode(self::str($d['payload'] ?? null), true),
            is_int($d['attempts'] ?? null) ? $d['attempts'] : 0,
            is_int($d['pushed_at'] ?? null) ? $d['pushed_at'] : 0,
            $json,
        );
    }

    private static function str(mixed $v): string
    {
        return is_string($v) ? $v : '';
    }

    public function job(): object
    {
        $job = unserialize($this->payload, ['allowed_classes' => true]);
        if (! is_object($job)) {
            throw new QueueException(sprintf('Job %s of class %s could not be unserialized.', $this->id, $this->class));
        }

        return $job;
    }

    public function attempted(): self
    {
        return new self($this->id, $this->queue, $this->class, $this->payload, $this->attempts + 1, $this->pushedAt, $this->reservation);
    }

    public function reserved(string $reservation): self
    {
        return new self($this->id, $this->queue, $this->class, $this->payload, $this->attempts, $this->pushedAt, $reservation);
    }

    public function toJson(): string
    {
        return json_encode([
            'id' => $this->id,
            'queue' => $this->queue,
            'class' => $this->class,
            'payload' => base64_encode($this->payload),
            'attempts' => $this->attempts,
            'pushed_at' => $this->pushedAt,
        ], JSON_THROW_ON_ERROR);
    }
}
