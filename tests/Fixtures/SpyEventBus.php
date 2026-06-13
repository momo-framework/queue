<?php

/**
 * Part of Momo Framework.
 *
 * © Momo Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author    Vahe Sargsyan <w33bvGL>
 * @copyright Momo Framework
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 * @link      https://github.com/momo-framework
 */

declare(strict_types=1);

namespace Momo\Queue\Tests\Fixtures;

use Momo\Events\Contracts\DomainEventInterface;
use Momo\Events\Contracts\EventBusInterface;
use Momo\Events\Contracts\EventListenerInterface;

/**
 * Test double that records every published event for assertions.
 */
final class SpyEventBus implements EventBusInterface
{
    /** @var list<DomainEventInterface> */
    public array $published = [];

    public function publish(DomainEventInterface ...$events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }

    public function subscribe(string $eventClass, EventListenerInterface $listener): void
    {
    }

    /**
     * Count published events of a given concrete type.
     *
     * @param class-string<DomainEventInterface> $eventClass
     * @return int<0, max>
     */
    public function countOf(string $eventClass): int
    {
        return \count(\array_filter(
            $this->published,
            static fn (DomainEventInterface $event): bool => $event instanceof $eventClass,
        ));
    }
}
