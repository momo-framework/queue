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

namespace Momo\Queue\Tests\Fixtures\Billing\Application\Handlers;

use Momo\Bus\Handler;
use Momo\Queue\Tests\Fixtures\Billing\Application\Commands\ArchiveInvoices;
use Momo\Queue\Tests\Fixtures\Reports\Ran;

/** @internal */
final readonly class ArchiveInvoicesHandler extends Handler
{
    public function handle(ArchiveInvoices $command): null
    {
        Ran::$list[] = 'archived ' . $command->year;

        return null;
    }
}
