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

namespace Momo\Queue\Contracts;

use Momo\Queue\Queue\Envelope;
use Momo\Queue\Support\FailedJobRecord;
use Throwable;

/**
 * Dead-letter storage for envelopes that exhausted their retry budget.
 */
interface FailedJobStoreInterface
{
    /**
     * Persist a permanently failed envelope together with its last error.
     *
     * @return non-empty-string the stored record id
     */
    public function store(Envelope $envelope, Throwable $exception): string;

    /**
     * All failed records, most recent first.
     *
     * @return list<FailedJobRecord>
     */
    public function all(): array;

    /**
     * Drop a single failed record by id. Returns false when it was not found.
     *
     * @param non-empty-string $id
     */
    public function forget(string $id): bool;

    /**
     * Remove every failed record.
     *
     * @return int<0, max> number of records removed
     */
    public function flush(): int;
}
