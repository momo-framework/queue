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

namespace Momo\Queue\Tests\Unit;

use ArrayObject;
use Closure;
use Momo\Bus\Command;
use Momo\Bus\Contracts\Middleware;
use Momo\Bus\HandlerMap;
use Momo\Bus\MessageBus;
use Momo\Bus\Query;
use Momo\Bus\Stage;
use Momo\Queue\Bus\QueueCommands;
use Momo\Queue\Bus\QueuedCommand;
use Momo\Queue\Dispatcher;
use Momo\Queue\Handlers;
use Momo\Queue\Tests\Fixtures\Reports\CurrentUser;
use Momo\Queue\Tests\Fixtures\Reports\ExportCalls;
use Momo\Queue\Tests\Fixtures\Reports\ExportCallsHandler;
use Momo\Queue\Tests\Fixtures\Reports\Ran;
use Momo\Queue\Transport\MemoryTransport;
use Momo\Queue\Worker;
use Momo\Queue\WorkerOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
#[CoversClass(QueueCommands::class)]
#[CoversClass(QueuedCommand::class)]
final class QueuedCommandTest extends TestCase
{
    private int $now = 1_000;

    /** @var list<string> */
    private array $checked = [];

    protected function setUp(): void
    {
        Ran::$list = [];
        CurrentUser::$id = null;
        $this->checked = [];
    }

    #[Test]
    public function the_sender_gets_null_and_the_worker_runs_it_as_the_sender(): void
    {
        $transport = new MemoryTransport(fn (): int => $this->now);
        [$bus, $handlers] = $this->bus($transport);

        CurrentUser::$id = 'ann';
        self::assertNull($bus->dispatch(new ExportCalls('2026-10')));
        CurrentUser::$id = null;

        self::assertSame([], Ran::$list);
        self::assertSame(1, $transport->size('reports'));

        new Worker($transport, $handlers)->run(new WorkerOptions(queues: ['reports'], stopWhenEmpty: true));

        self::assertSame(['2026-10 for ann'], Ran::$list);
        self::assertSame(['ann', 'ann'], $this->checked);
        self::assertNull(CurrentUser::$id);
    }

    #[Test]
    public function retries_follow_the_attribute_of_the_command(): void
    {
        $transport = new MemoryTransport(fn (): int => $this->now);
        [$bus, $handlers] = $this->bus($transport);
        $bus->dispatch(new ExportCalls('2026-09', fail: true));

        $worker = new Worker($transport, $handlers);
        $worker->run(new WorkerOptions(queues: ['reports'], stopWhenEmpty: true));
        $this->now += 5;
        $worker->run(new WorkerOptions(queues: ['reports'], stopWhenEmpty: true));

        self::assertSame(0, $transport->size('reports'));
        self::assertCount(1, $transport->failed());
    }

    /** @return array{MessageBus, Handlers} */
    private function bus(MemoryTransport $transport): array
    {
        $middleware = new ArrayObject();
        $jobs = new ArrayObject();
        $bus = new MessageBus(new HandlerMap([new ExportCallsHandler()]), $middleware);
        $handlers = new Handlers($jobs);
        $queue = new QueueCommands(new Dispatcher($transport), $bus, [new CurrentUser()]);

        $middleware->append($queue);
        $middleware->append($this->authorize());

        $jobs->append($queue);

        return [$bus, $handlers];
    }

    private function authorize(): Middleware
    {
        $record = function (): void {
            $this->checked[] = CurrentUser::$id ?? 'nobody';
        };

        return new readonly class($record) implements Middleware {
            public function __construct(private Closure $record) {}

            public function stage(): Stage
            {
                return Stage::Authorize;
            }

            public function handle(Command|Query $message, Closure $next): mixed
            {
                ($this->record)();

                return $next($message);
            }
        };
    }
}
