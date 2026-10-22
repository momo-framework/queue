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

namespace Momo\Queue\Queue;

/**
 * Ordering rules shared by the queue's ready and delayed heaps.
 */
final class Comparators
{
    /**
     * Highest priority first; equal priority preserves FIFO insertion order.
     *
     * @return callable(Envelope, Envelope): int
     */
    public static function byPriority(): callable
    {
        return static function (Envelope $a, Envelope $b): int {
            $byPriority = $b->priority <=> $a->priority;

            return $byPriority !== 0 ? $byPriority : $a->sequence <=> $b->sequence;
        };
    }

    /**
     * Earliest availability first; ties broken by FIFO insertion order.
     *
     * @return callable(Envelope, Envelope): int
     */
    public static function byAvailability(): callable
    {
        return static function (Envelope $a, Envelope $b): int {
            $byAvailability = $a->availableAt <=> $b->availableAt;

            return $byAvailability !== 0 ? $byAvailability : $a->sequence <=> $b->sequence;
        };
    }
}
