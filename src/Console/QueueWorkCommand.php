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

use Momo\Console\Attributes\AsCommand;
use Momo\Console\Attributes\Option;
use Momo\Console\Command;
use Momo\Kernel\Security\Signer;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\Envelope;
use Momo\Queue\Handlers;
use Momo\Queue\Worker;
use Momo\Queue\WorkerOptions;
use Swoole\Process;
use Throwable;

#[AsCommand(name: 'queue:work', description: 'Run queued jobs until stopped, several at once in coroutines')]
#[Option(name: 'queue', description: 'Queues to poll in order, comma-separated', default: 'default')]
#[Option(name: 'concurrency', description: 'Jobs run at once, one coroutine each', default: '1')]
#[Option(name: 'max-jobs', description: 'Stop after this many jobs', default: '0')]
#[Option(name: 'stop-when-empty', description: 'Stop when the queues are empty', noValue: true)]
#[Option(name: 'memory', description: 'Stop above this many MB so the supervisor restarts the process', default: '256')]
final class QueueWorkCommand extends Command
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Handlers $handlers,
        private readonly Signer $signer,
        private readonly int $visibility = 90,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $queues = array_values(array_filter(array_map(trim(...), explode(',', $this->optionText('queue', 'default'))), static fn (string $q): bool => $q !== ''));
        $options = new WorkerOptions(
            queues: $queues === [] ? ['default'] : $queues,
            concurrency: max(1, $this->number('concurrency', 1)),
            maxJobs: $this->number('max-jobs', 0),
            stopWhenEmpty: $this->option('stop-when-empty') === true,
            visibility: $this->visibility,
            memoryMb: $this->number('memory', 256),
        );

        $worker = new Worker($this->transport, $this->handlers, $this->signer, function (string $status, Envelope|null $e, Throwable|null $error, float $ms): void {
            if (! $e instanceof Envelope) {
                $this->output->writeln(sprintf('  <fg=gray>%s</>  <fg=red>queue unreachable</>  <fg=gray>%s</>', date('H:i:s'), $error?->getMessage() ?? ''));

                return;
            }

            $line = sprintf('  <fg=gray>%s</>  %s  %s  <fg=gray>#%d %.0f ms</>', date('H:i:s'), match ($status) {
                'processed' => '<fg=green>done</>',
                'retried' => '<fg=yellow>retry</>',
                default => '<fg=red>failed</>',
            }, $e->class, $e->attempts, $ms);
            $this->output->writeln($error instanceof Throwable ? $line . '  <fg=gray>' . $error->getMessage() . '</>' : $line);
        });

        $this->trapSignals($worker);
        $this->output->writeln(sprintf('  <options=bold>momo queue</>  <fg=gray>%s · %d at once</>', implode(', ', $options->queues), $options->concurrency));

        $done = $worker->run($options);
        $this->output->writeln(sprintf('  <fg=gray>stopped after %d jobs</>', $done));

        return self::SUCCESS;
    }

    private function optionText(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function trapSignals(Worker $worker): void
    {
        $stop = static function () use ($worker): void {
            $worker->stop();
        };

        if (extension_loaded('swoole')) {
            Process::signal(SIGTERM, $stop);
            Process::signal(SIGINT, $stop);

            return;
        }

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, $stop);
            pcntl_signal(SIGINT, $stop);
        }
    }

    private function number(string $name, int $default): int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : $default;
    }
}
