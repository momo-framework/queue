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

/**
 * Emitted after a job's handle() returns successfully.
 */
final readonly class JobProcessed extends DomainEvent
{
    public function __construct(
        public Envelope $envelope,
    ) {
        parent::__construct();
    }
}
