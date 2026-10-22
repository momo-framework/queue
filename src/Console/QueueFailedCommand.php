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

use function date;

use Momo\Console\Attributes\AsCommand;
use Momo\Console\Command;
use Momo\Queue\Contracts\FailedJobStoreInterface;

/**
 * Lists jobs that exhausted their retry budget and landed in the dead-letter store.
 */
#[AsCommand(name: 'queue:failed', description: 'List jobs that failed permanently')]
final class QueueFailedCommand extends Command
{
    public function __construct(
        private readonly FailedJobStoreInterface $store,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $records = $this->store->all();

        if ($records === []) {
            $this->success('No failed jobs.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                $record->id,
                $record->jobClass,
                (string) $record->attempts,
                $record->exceptionClass,
                date('Y-m-d H:i:s', $record->failedAt),
            ];
        }

        $this->table(['ID', 'Job', 'Attempts', 'Exception', 'Failed At'], $rows);

        return self::SUCCESS;
    }
}
