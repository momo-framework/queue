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

use Momo\Kernel\Security\Signer;
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
        public string $signature = '',
    ) {}

    public static function wrap(object $job, string $queue, Signer $signer): self
    {
        $id = bin2hex(random_bytes(12));
        $payload = serialize($job);

        return new self($id, $queue, $job::class, $payload, 0, time(), '', $signer->sign(self::signed($id, $queue, $job::class, $payload)));
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
            self::str($d['signature'] ?? null),
        );
    }

    private static function str(mixed $v): string
    {
        return is_string($v) ? $v : '';
    }

    private static function signed(string $id, string $queue, string $class, string $payload): string
    {
        return implode("\0", [$id, $queue, $class, $payload]);
    }

    public function job(Signer $signer): object
    {
        if (! $signer->verify(self::signed($this->id, $this->queue, $this->class, $this->payload), $this->signature)) {
            throw new QueueException(sprintf('Job %s of class %s is not signed by this app: it was pushed with another APP_KEY or not by the app at all. It is not opened.', $this->id, $this->class));
        }

        $job = unserialize($this->payload, ['allowed_classes' => true]);
        if (! $job instanceof $this->class) {
            throw new QueueException(sprintf('Job %s of class %s could not be unserialized.', $this->id, $this->class));
        }

        return $job;
    }

    public function attempted(): self
    {
        return clone ($this, ['attempts' => $this->attempts + 1]);
    }

    public function reserved(string $reservation): self
    {
        return clone ($this, ['reservation' => $reservation]);
    }

    public function fresh(int $pushedAt): self
    {
        return clone ($this, ['attempts' => 0, 'pushedAt' => $pushedAt, 'reservation' => '']);
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
            'signature' => $this->signature,
        ], JSON_THROW_ON_ERROR);
    }
}
