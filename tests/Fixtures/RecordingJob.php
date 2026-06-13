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

use Momo\Queue\Job\AbstractJob;

/**
 * Records each successful run; used to assert the worker executed handle().
 */
final class RecordingJob extends AbstractJob
{
    public int $runs = 0;

    public function handle(): void
    {
        $this->runs++;
    }
}
