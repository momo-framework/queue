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
use Momo\Console\Command;
use Momo\Queue\Dispatcher;
use Momo\Queue\Handlers;

#[AsCommand(name: 'queue:list', description: 'List every job class with its handler, queue and attempts')]
final class QueueListCommand extends Command
{
    public function __construct(private readonly Handlers $handlers, private readonly string $defaultQueue = 'default')
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $rows = [];
        foreach ($this->handlers->describe() as $job => $handler) {
            $spec = Dispatcher::attributeOf($job);
            $rows[] = [$job, $handler, $spec->queue ?? $this->defaultQueue, (string) $spec->attempts];
        }

        $rows === [] ? $this->info('No job handlers.') : $this->table(['Job', 'Handler', 'Queue', 'Attempts'], $rows);

        return self::SUCCESS;
    }
}
