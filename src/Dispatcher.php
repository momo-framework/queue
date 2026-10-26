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

namespace Momo\Queue;

use Momo\Queue\Contracts\Queue;
use Momo\Queue\Contracts\Transport;
use ReflectionClass;

/**
 * Wraps a job in an envelope and pushes it to the transport. With no
 * transport (QUEUE_CONNECTION=sync) the handler runs right away, which is
 * what development and tests usually want.
 *
 * @internal
 */
final class Dispatcher implements Queue
{
    /** @var array<class-string, Job> */
    private static array $attributes = [];

    public function __construct(
        private readonly Transport|null $transport,
        private readonly Handlers $handlers,
        private readonly string $defaultQueue = 'default',
    ) {}

    public static function attributeOf(string $class): Job
    {
        if (! class_exists($class)) {
            return new Job();
        }

        return self::$attributes[$class] ??= (new ReflectionClass($class)->getAttributes(Job::class)[0] ?? null)?->newInstance() ?? new Job();
    }

    public function dispatch(object $job, int $delay = 0): string
    {
        $envelope = Envelope::wrap($job, self::attributeOf($job::class)->queue ?? $this->defaultQueue);

        if (! $this->transport instanceof Transport) {
            ($this->handlers->of($job::class))($job);

            return $envelope->id;
        }

        $this->transport->push($envelope, max(0, $delay));

        return $envelope->id;
    }
}
