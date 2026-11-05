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

namespace Momo\Queue\Tests\Fixtures\Reports;

use Momo\Bus\Handler;
use RuntimeException;

final readonly class ExportCallsHandler extends Handler
{
    public function handle(ExportCalls $command): null
    {
        if ($command->fail) {
            throw new RuntimeException('disk full');
        }

        Ran::$list[] = $command->month . ' for ' . (CurrentUser::$id ?? 'nobody');

        return null;
    }
}
