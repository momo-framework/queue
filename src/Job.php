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

namespace Momo\Queue;

use Attribute;

/**
 * How a job class is queued and retried:
 *
 *   #[Job(queue: 'mail', attempts: 5, backoff: [10, 60, 300])]
 *   final readonly class SendInvoice { public function __construct(public int $invoiceId) {} }
 *
 * Without the attribute a job goes to the default queue with 3 attempts
 * and exponential backoff.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Job
{
    /**
     * @param null|string $queue    queue name, null for queue.queue
     * @param int         $attempts tries before the job is moved to the failed jobs
     * @param list<int>   $backoff  seconds before each retry, the last repeated; empty for 2^n with jitter
     */
    public function __construct(
        public string|null $queue = null,
        public int $attempts = 3,
        public array $backoff = [],
    ) {}

    /**
     * Seconds before attempt $next, the first retry being 2.
     */
    public function delayBefore(int $next): int
    {
        if ($this->backoff !== []) {
            return $this->backoff[min($next - 2, count($this->backoff) - 1)] ?? 0;
        }

        // Full jitter (AWS Architecture Blog): spreads retries of jobs that
        // failed together, so they do not hit a recovering service at once.
        $cap = min(3600, 2 ** min($next, 12));

        return random_int(1, max(1, $cap));
    }
}
