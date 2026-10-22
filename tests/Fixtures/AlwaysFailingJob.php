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

namespace Momo\Queue\Tests\Fixtures;

use Momo\Queue\Attributes\AsJob;
use Momo\Queue\Job\AbstractJob;
use RuntimeException;
use Throwable;

/**
 * Always throws; counts how often failed() was invoked.
 */
#[AsJob(maxAttempts: 3)]
final class AlwaysFailingJob extends AbstractJob
{
    public int $failedCalls = 0;

    public Throwable|null $lastException = null;

    public function handle(): void
    {
        throw new RuntimeException('boom');
    }

    public function failed(Throwable $exception): void
    {
        $this->failedCalls++;
        $this->lastException = $exception;
    }
}
