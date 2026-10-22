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

namespace Momo\Queue\Support;

use Momo\Queue\Queue\Envelope;
use Throwable;

/**
 * Immutable description of a permanently failed job, suitable for inspection
 * via `queue:failed` or a dead-letter dashboard.
 */
final readonly class FailedJobRecord
{
    /**
     * @param non-empty-string $id
     * @param class-string     $jobClass
     * @param non-empty-string $exceptionClass
     * @param int<0, max>      $attempts
     * @param int<0, max>      $failedAt
     */
    public function __construct(
        public string $id,
        public string $jobClass,
        public string $exceptionClass,
        public string $message,
        public int $attempts,
        public int $failedAt,
    ) {}

    /**
     * Build a record from the envelope and throwable observed at failure time.
     *
     * @param non-empty-string $id
     * @param int<0, max>      $failedAt
     */
    public static function fromEnvelope(string $id, Envelope $envelope, Throwable $exception, int $failedAt): self
    {
        $exceptionClass = $exception::class;

        return new self(
            id: $id,
            jobClass: $envelope->job::class,
            exceptionClass: $exceptionClass,
            message: $exception->getMessage(),
            attempts: $envelope->attempts,
            failedAt: $failedAt,
        );
    }
}
