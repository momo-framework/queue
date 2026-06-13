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

return [
    /*
     | Default exponential backoff applied to retried jobs that do not declare
     | their own HasBackoffPolicyInterface. Delay before the first retry equals
     | base_seconds, multiplied by `multiplier` on each subsequent retry and
     | capped at max_seconds.
     */
    'backoff' => [
        'base_seconds' => 5,
        'max_seconds'  => 3600,
        'multiplier'   => 2.0,
    ],

    /*
     | Default worker runtime limits. The memory ceiling lets the runtime
     | guardian recycle a worker before an unbounded leak degrades latency.
     */
    'worker' => [
        'sleep_seconds'   => 1,
        'memory_limit_mb' => 256,
        'max_jobs'        => 0,
    ],
];
