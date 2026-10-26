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

use Closure;
use Momo\Queue\Exceptions\QueueException;
use ReflectionMethod;
use ReflectionNamedType;

use function sprintf;

/**
 * Job class to handler, read from the __invoke signatures once per
 * worker.
 *
 * @internal
 */
final class Handlers
{
    /** @var null|array<class-string, Closure(object): mixed> */
    private array|null $map = null;

    /** @var array<class-string, class-string> */
    private array $names = [];

    /**
     * @param iterable<Contracts\JobHandler> $handlers
     */
    public function __construct(private readonly iterable $handlers = []) {}

    /**
     * @return Closure(object): mixed
     */
    public function of(string $class): Closure
    {
        $map = $this->map ??= $this->build();

        return $map[$class] ?? throw new QueueException(sprintf('No handler for job %s. Add a JobHandler with __invoke(%s $job) to Presentation/Queue of its module.', $class, mb_substr($class, (int) mb_strrpos($class, '\\') + 1)));
    }

    /**
     * @return array<class-string, class-string> job class => handler class, for queue:list
     */
    public function describe(): array
    {
        $this->map ??= $this->build();
        ksort($this->names);

        return $this->names;
    }

    /**
     * @return array<class-string, Closure(object): mixed>
     */
    private function build(): array
    {
        $map = [];
        foreach ($this->handlers as $handler) {
            $method = new ReflectionMethod($handler, '__invoke');
            $params = $method->getParameters();
            $type = ($params[0] ?? null)?->getType();
            $job = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;
            if ($job === null || ! class_exists($job) || count($params) !== 1) {
                throw new QueueException(sprintf('%s::__invoke() must take exactly one parameter typed with the job class.', $handler::class));
            }

            if (isset($map[$job])) {
                throw new QueueException(sprintf('Job %s has two handlers, %s and %s.', $job, $this->names[$job], $handler::class));
            }

            $call = $method->getClosure($handler);
            $map[$job] = static fn (object $j): mixed => $call($j);
            $this->names[$job] = $handler::class;
        }

        return $map;
    }
}
