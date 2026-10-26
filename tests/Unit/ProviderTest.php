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

use Momo\Kernel\Application;
use Momo\Queue\Contracts\Queue;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Handlers;
use Momo\Queue\QueueServiceProvider;
use Momo\Queue\Tests\Fixtures\Billing\Application\SendInvoice;
use Momo\Queue\Tests\Fixtures\Billing\Module;
use Momo\Queue\Tests\Fixtures\Billing\Presentation\Queue\SendInvoiceHandler;
use Momo\Queue\Worker;
use Momo\Queue\WorkerOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueueServiceProvider::class)]
final class ProviderTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/momo-queue-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/config', 0o755, true);
        mkdir($this->base . '/bootstrap/cache', 0o755, true);
        file_put_contents($this->base . '/bootstrap/cache/packages.php', '<?php return ' . var_export([
            'providers' => [QueueServiceProvider::class],
            'modules' => [],
            'configs' => ['queue' => dirname(__DIR__, 2) . '/config/queue.php'],
        ], true) . ';');
        file_put_contents($this->base . '/config/app.php', '<?php return ' . var_export(['modules' => [Module::class]], true) . ';');
        file_put_contents($this->base . '/config/queue.php', '<?php return ' . var_export(['transport' => 'memory'], true) . ';');
        SendInvoiceHandler::$sent = [];
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
        self::assertSame([SendInvoice::class => SendInvoiceHandler::class], $app->make(Handlers::class)->describe());
    }
}
