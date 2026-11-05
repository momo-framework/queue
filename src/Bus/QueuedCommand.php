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

namespace Momo\Queue\Bus;

use Momo\Bus\Command;
use Momo\Bus\Queued;
use Momo\Queue\Job;
use ReflectionClass;

/**
 * A command of #[Queued] on its way to a worker, with the state of every
 * context of the bus at the moment it was sent.
 *
 * @internal
 */
final readonly class QueuedCommand
{
    /**
     * @param Command<mixed>                      $command
     * @param array<string, array<string, mixed>> $context by class of the context
     */
    public function __construct(
        public Command $command,
        public array $context = [],
    ) {}

    /** @param Command<mixed> $command */
    public static function queuedOf(Command $command): Queued|null
    {
        return (new ReflectionClass($command)->getAttributes(Queued::class)[0] ?? null)?->newInstance();
    }

    /** The queue and the retries #[Queued] of the command asks for. */
    public function job(): Job
    {
        $queued = self::queuedOf($this->command);

        return new Job($queued?->queue, $queued->attempts ?? 3, $queued->backoff ?? []);
    }
}
