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

namespace Momo\Queue\Bus;

use Momo\Bus\Contracts\Bus;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * momo-bus can be installed and still not booted by the app: then there is
 * no bus to send queued commands back to, and the queue works with jobs only.
 *
 * @internal
 */
final readonly class WithoutBus implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->has(Bus::class)) {
            $container->removeDefinition(QueueCommands::class);
        }
    }
}
