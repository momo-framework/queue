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

use Momo\Bus\BusServiceProvider;
use Momo\Bus\Contracts\Bus;
use Momo\Events\EventsServiceProvider;
use Momo\Kernel\Application;
use Momo\Queue\Amqp\AmqpTransport;
use Momo\Queue\Bus\QueueCommands;
use Momo\Queue\Bus\QueuedCommand;
use Momo\Queue\Contracts\Queue;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Handlers;
use Momo\Queue\QueueServiceProvider;
use Momo\Queue\Tests\Fixtures\Billing\Application\Commands\ArchiveInvoices;
use Momo\Queue\Tests\Fixtures\Billing\Application\SendInvoice;
use Momo\Queue\Tests\Fixtures\Billing\Module;
use Momo\Queue\Tests\Fixtures\Billing\Presentation\Queue\SendInvoiceHandler;
use Momo\Queue\Tests\Fixtures\Reports\Ran;
use Momo\Queue\Worker;
use Momo\Queue\WorkerOptions;
use Momo\Server\ServerServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
#[CoversClass(QueueServiceProvider::class)]
final class ProviderTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/momo-queue-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/config', 0o755, true);
        mkdir($this->base . '/bootstrap/cache', 0o755, true);
        $this->providers([EventsServiceProvider::class, BusServiceProvider::class, ServerServiceProvider::class, QueueServiceProvider::class]);
        file_put_contents($this->base . '/config/app.php', '<?php return ' . var_export(['modules' => [Module::class]], true) . ';');
        file_put_contents($this->base . '/config/queue.php', '<?php return ' . var_export(['transport' => 'memory'], true) . ';');
        SendInvoiceHandler::$sent = [];
        Ran::$list = [];
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->base));
    }

    #[Test]
    public function the_handler_in_presentation_queue_of_a_module_runs_the_job(): void
    {
        $app = new Application($this->base);
        $app->boot();

        $app->make(Queue::class)->dispatch(new SendInvoice(9));
        new Worker($app->make(Transport::class), $app->make(Handlers::class))->run(new WorkerOptions(queues: ['mail'], stopWhenEmpty: true));

        self::assertSame([9], SendInvoiceHandler::$sent);
        self::assertSame([QueuedCommand::class => QueueCommands::class, SendInvoice::class => SendInvoiceHandler::class], $app->make(Handlers::class)->describe());
    }

    #[Test]
    public function a_queued_command_of_a_module_waits_for_the_worker_when_the_bus_is_booted(): void
    {
        $app = new Application($this->base);
        $app->boot();

        self::assertNull($app->make(Bus::class)->dispatch(new ArchiveInvoices(2025)));
        self::assertSame([], Ran::$list);

        new Worker($app->make(Transport::class), $app->make(Handlers::class))->run(new WorkerOptions(queues: ['archive'], stopWhenEmpty: true));

        self::assertSame(['archived 2025'], Ran::$list);
    }

    #[Test]
    public function the_queue_goes_to_rabbitmq_unless_a_test_asks_for_memory(): void
    {
        file_put_contents($this->base . '/config/queue.php', '<?php return [];');
        $app = new Application($this->base);
        $app->boot();

        self::assertInstanceOf(AmqpTransport::class, $app->make(Transport::class));
    }

    /** @param list<class-string> $providers */
    private function providers(array $providers): void
    {
        file_put_contents($this->base . '/bootstrap/cache/packages.php', '<?php return ' . var_export([
            'providers' => $providers,
            'modules' => [],
            'configs' => ['queue' => dirname(__DIR__, 2) . '/config/queue.php'],
        ], true) . ';');
    }
}
