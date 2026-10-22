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

namespace Momo\Queue\FailedJob;

use function array_reverse;
use function array_values;
use function bin2hex;
use function count;

use Momo\Queue\Contracts\ClockInterface;
use Momo\Queue\Contracts\FailedJobStoreInterface;
use Momo\Queue\Queue\Envelope;
use Momo\Queue\Support\FailedJobRecord;

use function random_bytes;

use Throwable;

/**
 * Process-local dead-letter store. Suitable for single-worker setups and tests;
 * production deployments bind a persistent implementation so records survive a
 * worker restart triggered by the runtime guardian.
 */
final class InMemoryFailedJobStore implements FailedJobStoreInterface
{
    /** @var array<non-empty-string, FailedJobRecord> insertion-ordered map */
    private array $records = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function store(Envelope $envelope, Throwable $exception): string
    {
        $id = bin2hex(random_bytes(16));

        $this->records[$id] = FailedJobRecord::fromEnvelope($id, $envelope, $exception, $this->clock->now());

        return $id;
    }

    public function all(): array
    {
        return array_reverse(array_values($this->records));
    }

    public function forget(string $id): bool
    {
        if (! isset($this->records[$id])) {
            return false;
        }

        unset($this->records[$id]);

        return true;
    }

    public function flush(): int
    {
        $count = count($this->records);
        $this->records = [];

        return $count;
    }
}
