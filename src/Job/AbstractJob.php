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

namespace Momo\Queue\Job;

use Momo\Queue\Attributes\AsJob;
use Momo\Queue\Contracts\JobInterface;
use Momo\Queue\Exceptions\InvalidJobException;
use ReflectionClass;
use Throwable;

/**
 * Convenience base for jobs: reads retry/priority defaults from {@see AsJob}
 * and supplies a no-op {@see failed()} that concrete jobs may override.
 */
abstract class AbstractJob implements JobInterface
{
    /** @var array<class-string, AsJob> resolved attribute cache, keyed by class */
    private static array $metadata = [];

    abstract public function handle(): void;

    public function failed(Throwable $exception): void
    {
    }

    public function maxAttempts(): int
    {
        $attempts = $this->metadata()->maxAttempts;

        if ($attempts < 1) {
            throw InvalidJobException::nonPositiveMaxAttempts(static::class, $attempts);
        }

        return $attempts;
    }

    /**
     * Dispatch priority declared via {@see AsJob}; higher runs first.
     */
    public function priority(): int
    {
        return $this->metadata()->priority;
    }

    private function metadata(): AsJob
    {
        $class = static::class;

        if (isset(self::$metadata[$class])) {
            return self::$metadata[$class];
        }

        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsJob::class);

        $instance = $attributes === []
            ? new AsJob()
            : $attributes[0]->newInstance();

        return self::$metadata[$class] = $instance;
    }
}
