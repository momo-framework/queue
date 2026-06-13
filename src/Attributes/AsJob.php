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

namespace Momo\Queue\Attributes;

use Attribute;

/**
 * Declarative defaults for a job class. Read by {@see \Momo\Queue\Job\AbstractJob}
 * so a job's retry budget and dispatch priority live next to the class instead
 * of in scattered enqueue calls.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsJob
{
    /**
     * @param positive-int $maxAttempts initial run plus retries
     * @param int          $priority    higher dispatches first
     */
    public function __construct(
        public int $maxAttempts = 3,
        public int $priority = 0,
    ) {
    }
}
