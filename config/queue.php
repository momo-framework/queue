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
    // › Exponential Backoff Mechanics
    // ─────────────────────────────────────────────────────────────────── ⚛ ───
    //   Governs the automated progressive calculation of delays injected
    //   between sequential job retries for structural components missing an
    //   explicit HasBackoffPolicyInterface declaration. The formula utilizes the
    //   multiplier exponent to progressively increase delay intervals up to the
    //   max_seconds cap, preventing network thrashing.
    //
    //   Supported Options:
    //     • "base_seconds" - Initial temporal delay assigned to the first retry.
    //     • "max_seconds"  - Hard upper bound limit for exponential calculations.
    //     • "multiplier"   - Float value applied to calculate subsequent delay tiers.
    //

    'backoff' => [
        'base_seconds' => 5,
        'max_seconds'  => 3600,
        'multiplier'   => 2.0,
    ],

    // › Asynchronous Worker Lifecycle Guard
    // ─────────────────────────────────────────────────────────────────── ⚛ ───
    //   Configures structural runtime limitations for the background worker processes
    //   operating within the Swoole daemonized runtime loop. The memory ceiling
    //   allows the orchestrator to preemptively recycle worker processes before
    //   unbounded data-leaks compromise total operating system performance.
    //
    //   ⚠ In long-lived Swoole execution contexts, unsetting large internal static
    //   variables or handling extensive object graphs can trigger progressive
    //   state bloating. Configuring memory_limit_mb acts as a critical failsafe,
    //   gracefully restarting the worker once the threshold is crossed.
    //
    //   Supported Options:
    //     • "sleep_seconds"   - Coroutine-friendly pause duration when queue is empty.
    //     • "memory_limit_mb" - Maximum RAM ceiling allocated per isolated worker.
    //     • "max_jobs"        - Hard execution loop count before process recycling (0 = off).
    //

    'worker' => [
        'sleep_seconds'   => 1,
        'memory_limit_mb' => 256,
        'max_jobs'        => 0,
    ],
];