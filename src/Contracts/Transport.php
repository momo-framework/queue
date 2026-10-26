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

namespace Momo\Queue\Contracts;

use Momo\Queue\Envelope;
use Momo\Queue\FailedJob;
use Throwable;

/**
 * Where jobs wait. pop() reserves a job for $visibility seconds: a worker
 * that dies with it lets the job reappear after that, so every job runs
 * at least once.
 */
interface Transport
{
    public function push(Envelope $envelope, int $delay = 0): void;

    /**
     * @phpstan-impure
     */
    public function pop(string $queue, int $visibility): Envelope|null;

    public function ack(Envelope $envelope): void;

    public function release(Envelope $envelope, int $delay): void;

    public function fail(Envelope $envelope, Throwable $error): void;

    public function size(string $queue): int;

    /**
     * @return list<FailedJob>
     */
    public function failed(): array;

    /**
     * Puts a failed job back on its queue with its attempts reset.
     */
    public function retry(string $id): bool;

    public function forget(string $id): bool;
}
