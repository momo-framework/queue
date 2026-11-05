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

use Closure;
use Momo\Bus\Contracts\Context;

/** The signed in user of a protocol, the way momo-auth keeps it. */
final class CurrentUser implements Context
{
    public static string|null $id = null;

    public function capture(): array
    {
        return self::$id === null ? [] : ['id' => self::$id];
    }

    public function restore(array $state, Closure $work): mixed
    {
        $before = self::$id;
        self::$id = is_string($state['id'] ?? null) ? $state['id'] : null;

        try {
            return $work();
        } finally {
            self::$id = $before;
        }
    }
}
