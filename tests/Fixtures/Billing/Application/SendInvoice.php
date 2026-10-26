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

namespace Momo\Queue\Tests\Fixtures\Billing\Application;

use Momo\Queue\Job;

/** @internal */
#[Job(queue: 'mail', attempts: 2, backoff: [0])]
final readonly class SendInvoice
{
    public function __construct(public int $invoiceId, public bool $fail = false, public float $takes = 0.0) {}
}
