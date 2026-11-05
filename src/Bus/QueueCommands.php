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

use Closure;
use Momo\Bus\Command;
use Momo\Bus\Contracts\Bus;
use Momo\Bus\Contracts\Context;
use Momo\Bus\Contracts\Middleware;
use Momo\Bus\Query;
use Momo\Bus\Stage;
use Momo\Queue\Contracts\JobHandler;
use Momo\Queue\Contracts\Queue;

/**
 * Both ends of a command of #[Queued]. On the bus of the sender it leaves
 * for the queue after the permission check and the sender gets null; in
 * the worker it comes back to the same bus within the contexts it was sent
 * in and runs the whole chain, the permission again included.
 *
 * @internal
 */
final class QueueCommands implements JobHandler, Middleware
{
    /** @var array<class-string, bool> */
    private array $queued = [];

    /** @var array<int, true> commands the worker runs now, by object id */
    private array $running = [];

    /**
     * @param iterable<Context> $contexts
     */
    public function __construct(
        private readonly Queue $queue,
        private readonly Bus $bus,
        private readonly iterable $contexts = [],
    ) {}

    public function __invoke(QueuedCommand $job): void
    {
        $command = $job->command;
        $work = fn (): mixed => $this->bus->dispatch($command);
        foreach ($this->contexts as $each) {
            $inner = $work;
            $state = $job->context[$each::class] ?? [];
            $work = static fn (): mixed => $each->restore($state, $inner);
        }

        $this->running[spl_object_id($command)] = true;

        try {
            $work();
        } finally {
            unset($this->running[spl_object_id($command)]);
        }
    }

    public function stage(): Stage
    {
        return Stage::Queue;
    }

    public function handle(Command|Query $message, Closure $next): mixed
    {
        if ($message instanceof Query || isset($this->running[spl_object_id($message)]) || ! ($this->queued[$message::class] ??= QueuedCommand::queuedOf($message) instanceof \Momo\Bus\Queued)) {
            return $next($message);
        }

        $context = [];
        foreach ($this->contexts as $each) {
            $context[$each::class] = $each->capture();
        }

        $this->queue->dispatch(new QueuedCommand($message, $context), QueuedCommand::queuedOf($message)->delay ?? 0);

        return null;
    }
}
