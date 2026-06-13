# momo/queue

Coroutine-aware Queue/Worker engine with job priorities and exponential backoff for high-load e-commerce workloads.

> 🇷🇺 Документация на русском: [README.ru.md](README.ru.md)

## Installation

```bash
composer require momo-framework/queue
```

The `QueueServiceProvider` is auto-discovered. It binds `QueueInterface`,
`WorkerInterface`, `FailedJobStoreInterface`, `BackoffPolicyInterface`,
`ClockInterface` and `SleeperInterface`, and registers the `queue:work` and
`queue:failed` console commands.

## Quick Start

Define a job:

```php
use Momo\Queue\Attributes\AsJob;
use Momo\Queue\Job\AbstractJob;

#[AsJob(maxAttempts: 5, priority: 10)]
final class SendOrderReceipt extends AbstractJob
{
    public function __construct(private readonly string $orderId) {}

    public function handle(): void
    {
        // ... deliver the receipt ...
    }

    public function failed(\Throwable $e): void
    {
        // compensating action after the retry budget is exhausted
    }
}
```

Dispatch it:

```php
$queue->push(new SendOrderReceipt($orderId), priority: 10);
$queue->later(30, new SendOrderReceipt($orderId)); // available in 30s
```

Run a worker:

```bash
php bin/console queue:work            # long-running
php bin/console queue:work --once     # process a single job and exit
php bin/console queue:failed          # inspect the dead-letter store
```

## How it works

- **Priorities & delays** — `pop()` returns the highest-priority *ready*
  envelope; equal priorities keep FIFO order. Delayed jobs never surface early.
- **Retries** — a thrown job is retried until `maxAttempts()`, each retry
  scheduled by the configured `BackoffPolicyInterface` (default: exponential).
- **Dead-letter** — exhausted jobs land in the `FailedJobStoreInterface` and a
  `JobFailed` domain event is published.
- **Lifecycle events** — `JobProcessing`, `JobProcessed`, `JobFailed` are
  published on the Momo event bus for telemetry and notifications.
- **No blocking** — the worker idle-waits through `SleeperInterface`
  (Swoole-coroutine aware); handlers must never call `sleep()`.

## Configuration

See [`config/queue.php`](config/queue.php) and
[docs/en/configuration.md](docs/en/configuration.md).

## Documentation

- [Overview](docs/en/overview.md)
- [API reference](docs/en/api.md)
- [Configuration](docs/en/configuration.md)

## License

AGPL-3.0-or-later.
