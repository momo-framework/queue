<?php

/**
 * Part of Momo Framework.
 *
 * © Momo Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author    Vahe Sargsyan <w33bvGL>
 * @copyright Momo Framework
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
 */

declare(strict_types=1);

namespace Momo\Queue\Tests\Fixtures;

use Momo\Queue\Attributes\AsJob;
use Momo\Queue\Job\AbstractJob;
use RuntimeException;

/**
 * Fails a fixed number of times, then succeeds — exercises the retry path.
 */
#[AsJob(maxAttempts: 5)]
final class FlakyJob extends AbstractJob
{
    public int $attempts = 0;

    public function __construct(private readonly int $failTimes)
    {
    }

    public function handle(): void
    {
        $this->attempts++;

        if ($this->attempts <= $this->failTimes) {
            throw new RuntimeException('transient failure');
        }
    }
}
