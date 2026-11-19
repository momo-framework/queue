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

namespace Momo\Queue;

use DateTimeImmutable;
use Throwable;

final readonly class JobRun
{
    public function __construct(
        public RunStatus $status,
        public Envelope $envelope,
        public Throwable|null $error,
        public float $ms,
        public DateTimeImmutable $at,
    ) {}
}
