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

use Momo\Console\Attributes\Argument;
use Momo\Console\Attributes\AsCommand;
use Momo\Console\Command;
use Momo\Queue\Contracts\Transport;
use Momo\Queue\FailedJob;

#[AsCommand(name: 'queue:retry', description: 'Put failed jobs back on their queues')]
#[Argument(name: 'ids', description: 'Ids from queue:failed, or "all"', required: true, isArray: true)]
final class QueueRetryCommand extends Command
{
    public function __construct(private readonly Transport|null $transport)
    {
        parent::__construct();
    }

    protected function handle(): int
    {
        if (! $this->transport instanceof Transport) {
            $this->error('QUEUE_CONNECTION is sync: there are no failed jobs to retry.');

            return self::FAILURE;
        }

        $ids = $this->argument('ids');
        $ids = is_array($ids) ? array_values(array_filter($ids, is_string(...))) : [];
        if ($ids === ['all']) {
            $ids = array_map(static fn (FailedJob $f): string => $f->envelope->id, $this->transport->failed());
        }

        $retried = 0;
        foreach ($ids as $id) {
            $this->transport->retry($id) ? ++$retried : $this->warning("No failed job {$id}.");
        }

        $this->success(sprintf('%d jobs back on their queues.', $retried));

        return self::SUCCESS;
    }
}
