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

namespace Momo\Queue\Health;

use Momo\Kernel\Health\Check;
use Momo\Kernel\Health\Result;
use Momo\Queue\Amqp\Connections;
use Throwable;

final readonly class AmqpCheck implements Check
{
    public function __construct(private Connections $connections) {}

    public function name(): string
    {
        return 'amqp';
    }

    public function check(): Result
    {
        try {
            return $this->connections->link()->connection->isConnected() ? Result::up() : Result::down('Not connected to RabbitMQ.');
        } catch (Throwable $throwable) {
            $this->connections->discard();

            return Result::down('RabbitMQ does not answer: ' . $throwable->getMessage());
        }
    }
}
