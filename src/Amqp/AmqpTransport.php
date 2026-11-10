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

namespace Momo\Queue\Amqp;

use Closure;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Envelope;
use Momo\Queue\FailedJob;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

final readonly class AmqpTransport implements Transport
{
    private const int FAILED_SCAN = 1000;

    public function __construct(
        private Connections $connections,
        private string $failedQueue = 'momo.failed',
    ) {}

    public function push(Envelope $envelope, int $delay = 0): void
    {
        $this->run(function (Link $link) use ($envelope, $delay): void {
            $target = $this->declare($link, $envelope->queue);
            if ($delay > 0) {
                $target = $this->declareDelay($link, $envelope->queue, $delay);
            }

            $link->publish($this->message($envelope->toJson(), $envelope->id), $target);
        });
    }

    public function pop(string $queue, int $visibility): Envelope|null
    {
        return $this->run(function (Link $link) use ($queue): Envelope|null {
            $this->declare($link, $queue);
            $message = $link->channel->basic_get($queue);
            if (! $message instanceof AMQPMessage) {
                return null;
            }

            $tag = (string) $message->getDeliveryTag();
            $link->pending[$tag] = $message;
            $envelope = Envelope::fromJson($message->getBody())->attempted();
            if ($message->isRedelivered() === true) {
                $envelope = $envelope->attempted();
            }

            return $envelope->reserved($tag);
        });
    }

    public function ack(Envelope $envelope): void
    {
        $this->run(function (Link $link) use ($envelope): void {
            $this->settle($link, $envelope);
        });
    }

    public function release(Envelope $envelope, int $delay): void
    {
        $this->run(function (Link $link) use ($envelope, $delay): void {
            $target = $delay > 0 ? $this->declareDelay($link, $envelope->queue, $delay) : $this->declare($link, $envelope->queue);
            $link->publish($this->message($envelope->toJson(), $envelope->id), $target);
            $this->settle($link, $envelope);
        });
    }

    public function fail(Envelope $envelope, Throwable $error): void
    {
        $this->run(function (Link $link) use ($envelope, $error): void {
            $this->declare($link, $this->failedQueue);
            $body = json_encode(['envelope' => $envelope->toJson(), 'error' => $error::class . ': ' . $error->getMessage(), 'failed_at' => time()], JSON_THROW_ON_ERROR);
            $link->publish($this->message($body, $envelope->id), $this->failedQueue);
            $this->settle($link, $envelope);
        });
    }

    public function size(string $queue): int
    {
        return $this->run(function (Link $link) use ($queue): int {
            $this->declare($link, $queue);
            $info = $link->channel->queue_declare($queue, true);

            return is_array($info) && is_int($info[1] ?? null) ? $info[1] : 0;
        });
    }

    public function failed(): array
    {
        $jobs = [];
        $this->scanFailed(static function (FailedJob $job) use (&$jobs): null {
            $jobs[] = $job;

            return null;
        });

        return $jobs;
    }

    public function retry(string $id): bool
    {
        $found = false;
        $this->scanFailed(function (FailedJob $job) use ($id, &$found): bool|null {
            if ($found || $job->envelope->id !== $id) {
                return null;
            }

            $found = true;
            $e = $job->envelope;
            $this->push($e->fresh(time()));

            return true;
        });

        return $found;
    }

    public function forget(string $id): bool
    {
        $found = false;
        $this->scanFailed(static function (FailedJob $job) use ($id, &$found): bool|null {
            if ($found || $job->envelope->id !== $id) {
                return null;
            }

            $found = true;

            return true;
        });

        return $found;
    }

    /** @param Closure(FailedJob): (null|bool) $visit */
    private function scanFailed(Closure $visit): void
    {
        $this->run(function (Link $link) use ($visit): void {
            $this->declare($link, $this->failedQueue);
            $held = [];

            try {
                for ($i = 0; $i < self::FAILED_SCAN; $i++) {
                    $message = $link->channel->basic_get($this->failedQueue);
                    if (! $message instanceof AMQPMessage) {
                        break;
                    }

                    $held[] = $message;
                    $data = json_decode($message->getBody(), true);
                    if (! is_array($data) || ! is_string($data['envelope'] ?? null)) {
                        continue;
                    }

                    $job = new FailedJob(Envelope::fromJson($data['envelope']), is_string($data['error'] ?? null) ? $data['error'] : '', is_int($data['failed_at'] ?? null) ? $data['failed_at'] : 0);
                    if ($visit($job) === true) {
                        $message->ack();
                        array_pop($held);
                    }
                }
            } finally {
                foreach ($held as $message) {
                    $message->nack(true);
                }
            }
        });
    }

    private function settle(Link $link, Envelope $envelope): void
    {
        $message = $link->pending[$envelope->reservation] ?? null;
        unset($link->pending[$envelope->reservation]);
        $message?->ack();
    }

    private function declare(Link $link, string $queue): string
    {
        if (! isset($link->declared[$queue])) {
            $link->channel->queue_declare($queue, false, true, false, false);
            $link->declared[$queue] = true;
        }

        return $queue;
    }

    private function declareDelay(Link $link, string $queue, int $delay): string
    {
        $name = $queue . '.delay.' . $delay;
        if (! isset($link->declared[$name])) {
            $link->channel->queue_declare($name, false, true, false, false, false, new AMQPTable([
                'x-message-ttl' => $delay * 1000,
                'x-dead-letter-exchange' => '',
                'x-dead-letter-routing-key' => $queue,
                'x-expires' => $delay * 1000 + 60000,
            ]));
            $link->declared[$name] = true;
        }

        return $name;
    }

    private function message(string $body, string $id): AMQPMessage
    {
        return new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $id,
        ]);
    }

    /**
     * @template T
     *
     * @param Closure(Link): T $work
     *
     * @return T
     */
    private function run(Closure $work): mixed
    {
        try {
            return $work($this->connections->link());
        } catch (\PhpAmqpLib\Exception\AMQPExceptionInterface|\PhpAmqpLib\Exception\AMQPIOException $e) {
            $this->connections->discard();

            throw $e;
        }
    }
}
