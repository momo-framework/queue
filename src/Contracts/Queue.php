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

namespace Momo\Queue\Contracts;

/**
 * Puts jobs on the queue. A job is a plain object of public values; its
 * handler, a JobHandler in some module, runs it in a queue:work process.
 */
interface Queue
{
    /**
     * @param int $delay seconds before the job may run
     *
     * @return string the job id
     */
    public function dispatch(object $job, int $delay = 0): string;
}
