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
use Momo\Queue\Contracts\Transport;
use Momo\Queue\FailedJob;

#[AsCommand(name: 'queue:failed', description: 'List jobs that used up their attempts')]
final class QueueFailedCommand extends Command
{
    public function __construct(private readonly Transport|null $transport)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        $failed = $this->transport?->failed() ?? [];
        if ($failed === []) {
            $this->info('No failed jobs.');

            return self::SUCCESS;
        }

        $this->table(['Id', 'Queue', 'Job', 'Failed at', 'Error'], array_map(static fn (FailedJob $f): array => [
            $f->envelope->id,
            $f->envelope->queue,
            $f->envelope->class,
            date('Y-m-d H:i:s', $f->failedAt),
            mb_strimwidth($f->error, 0, 80, '...'),
        ], $failed));

        return self::SUCCESS;
    }
}
