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

use Momo\Queue\Exceptions\QueueException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

use function sprintf;

use Throwable;

/** @internal */
final class Link
{
    /** @var array<string, true> */
    public array $declared = [];

    /** @var array<int|string, AMQPMessage> */
    public array $pending = [];

    public function __construct(
        public readonly AMQPStreamConnection $connection,
        public readonly AMQPChannel $channel,
        private readonly float $timeout = 5.0,
    ) {
        $channel->set_nack_handler(static function (AMQPMessage $message): never {
            $id = $message->get_properties()['message_id'] ?? '';

            throw new QueueException(sprintf('RabbitMQ refused the message %s.', is_scalar($id) ? (string) $id : ''));
        });
        $channel->confirm_select();
    }

    public function publish(AMQPMessage $message, string $queue): void
    {
        $this->channel->basic_publish($message, '', $queue);
        $this->channel->wait_for_pending_acks($this->timeout);
    }

    public function close(): void
    {
        try {
            $this->channel->close();
            $this->connection->close();
        } catch (Throwable) {
        }
    }
}
