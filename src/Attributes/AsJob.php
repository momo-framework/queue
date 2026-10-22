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
    ) {}
}
