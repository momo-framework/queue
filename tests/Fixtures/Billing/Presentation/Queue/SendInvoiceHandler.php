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

namespace Momo\Queue\Tests\Fixtures\Billing\Presentation\Queue;

use Momo\Queue\Contracts\JobHandler;
use Momo\Queue\Tests\Fixtures\Billing\Application\SendInvoice;
use RuntimeException;
use Swoole\Coroutine;

/** @internal */
final class SendInvoiceHandler implements JobHandler
{
    /** @var list<int> */
    public static array $sent = [];

    public function __invoke(SendInvoice $job): void
    {
        if ($job->takes > 0) {
            Coroutine::sleep($job->takes);
        }

        if ($job->fail) {
            throw new RuntimeException('SMTP refused');
        }

        self::$sent[] = $job->invoiceId;
    }
}
