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

namespace Momo\Queue\Console;

use function function_exists;
use function is_numeric;
use function max;

use Momo\Console\Attributes\AsCommand;
use Momo\Console\Attributes\Option;
use Momo\Console\Command;
use Momo\Queue\Contracts\WorkerInterface;
use Momo\Queue\Worker\WorkerOptions;

use function pcntl_async_signals;
use function pcntl_signal;

use const SIGINT;
use const SIGTERM;

/**
 * Starts a long-running queue worker that drains pending jobs.
 */
#[AsCommand(name: 'queue:work', description: 'Process queued jobs until stopped')]
#[Option(name: 'once', description: 'Process a single job and exit', noValue: true)]
#[Option(name: 'max-jobs', description: 'Exit after processing this many jobs (0 = unlimited)', default: '0')]
#[Option(name: 'sleep', description: 'Seconds to wait when no job is ready', default: '1')]
#[Option(name: 'memory', description: 'Restart threshold in MB (0 = no limit)', default: '256')]
final class QueueWorkCommand extends Command
{
    public function __construct(
        private readonly WorkerInterface $worker,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $once = (bool) $this->option('once');
        $options = new WorkerOptions(
            maxJobs: $once ? 1 : max(0, $this->intOption('max-jobs')),
            sleepSeconds: max(0, $this->intOption('sleep')),
            stopWhenEmpty: $once,
            memoryLimitMb: max(0, $this->intOption('memory')),
        );

        $this->registerSignalHandlers();

        $this->info($once ? 'Processing a single job…' : 'Queue worker started. Press Ctrl+C to stop.');

        $this->worker->run($options);

        $this->success('Queue worker stopped cleanly.');

        return self::SUCCESS;
    }

    private function intOption(string $name): int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : 0;
    }

    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_async_signals') || ! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function (): void {
            $this->worker->stop();
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}
