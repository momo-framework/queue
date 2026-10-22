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

namespace Momo\Queue\Events;

use Momo\Events\DomainEvent;
use Momo\Queue\Queue\Envelope;
use Throwable;

/**
 * Emitted once a job has exhausted its retry budget and was moved to the
 * failed-job store.
 */
final readonly class JobFailed extends DomainEvent
{
    public function __construct(
        public Envelope $envelope,
        public Throwable $exception,
    ) {
        parent::__construct();
    }
}
