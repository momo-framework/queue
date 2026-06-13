<?php

/**
 * Part of Momo Framework.
 *
 * © Momo Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Unauthorized copying, modification, or distribution of this file,
 * via any medium, is strictly prohibited without prior written permission
 * from the copyright holder.
 *
 * @author    Vahe Sargsyan <w33bvGL>
 * @copyright Momo Framework
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
 */

declare(strict_types=1);

namespace Momo\Queue\FailedJob;

use Momo\Queue\Contracts\ClockInterface;
use Momo\Queue\Contracts\FailedJobStoreInterface;
use Momo\Queue\Queue\Envelope;
use Momo\Queue\Support\FailedJobRecord;
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
    ) {
    }

    public function store(Envelope $envelope, Throwable $exception): string
    {
        $id = \bin2hex(\random_bytes(16));

        $this->records[$id] = FailedJobRecord::fromEnvelope($id, $envelope, $exception, $this->clock->now());

        return $id;
    }

    public function all(): array
    {
        return \array_reverse(\array_values($this->records));
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
        $count = \count($this->records);
        $this->records = [];

        return $count;
    }
}
