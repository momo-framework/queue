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
 * A job whose failed() handler itself throws; the worker must survive it.
 */
#[AsJob(maxAttempts: 1)]
final class HostileJob extends AbstractJob
{
    public function handle(): void
    {
        throw new RuntimeException('primary failure');
    }

    public function failed(Throwable $exception): void
    {
        throw new RuntimeException('failed() also exploded');
    }
}
