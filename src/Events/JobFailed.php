<?php

/**
 * Part of Momo Framework.
 *
 * © Momo Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Unauthorized copying, modification, or distribution of this file,
 * via any medium, is strictly prohibited without prior written permission
 * from the copyright holder.
 *
 * @author    Vahe Sargsyan <w33bvGL>
 * @copyright Momo Framework
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
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
        public readonly Envelope $envelope,
        public readonly Throwable $exception,
    ) {
        parent::__construct();
    }
}
