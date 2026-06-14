<div align="center">
  <img src="https://avatars.githubusercontent.com/u/255415480?s=200&v=4" alt="Momo Framework" width="120" height="120" />

  <h1>momo-framework/queue</h1>

  <p>
      Coroutine-aware priority queue with pluggable backoff strategies for <a href="https://github.com/momo-framework">Momo Framework</a>.
  </p>

  <p>
    <img src="https://github.com/momo-framework/queue/actions/workflows/ci.yml/badge.svg" alt="CI" />
    <img src="https://img.shields.io/packagist/v/momo-framework/queue.svg?style=flat" alt="Latest Version" />
    <img src="https://img.shields.io/packagist/dt/momo-framework/queue.svg?style=flat" alt="Total Downloads" />
    <img src="https://img.shields.io/badge/php-%3E%3D8.5-8892bf.svg" alt="PHP Version" />
    <img src="https://img.shields.io/badge/license-AGPL--3.0-blue.svg" alt="License" />
    <img src="https://img.shields.io/badge/coverage-100%25-brightgreen.svg" alt="Coverage" />
    <img src="https://img.shields.io/badge/PHPStan-level%2010-brightblue.svg" alt="PHPStan" />
  </p>
  <p>🇬🇧 English &nbsp;·&nbsp; 🇷🇺 <a href="README.ru.md">Русская версия</a></p>
</div>

---

## Overview

`momo-framework/queue` provides a coroutine-aware job queue engine backed by a priority min-heap. Jobs declare their retry budget and dispatch priority through a single `#[AsJob]` attribute. The `Worker` drains the queue in a tight loop, retrying failures with configurable backoff and moving exhausted jobs to a failed-job store.

The package integrates with `momo-framework/events`: `JobProcessing`, `JobProcessed`, and `JobFailed` domain events are published on every lifecycle transition, enabling telemetry and notifications without coupling them to the worker itself.

`InMemoryQueue` is the built-in driver. It uses two binary heaps — one for ready jobs ordered by priority, one for delayed jobs ordered by availability time — and is safe under Swoole's cooperative scheduler because every mutating operation completes without an I/O suspension point.

## Requirements

- PHP >= 8.5
- `momo-framework/kernel`
- `momo-framework/events`
- `ext-swoole` (optional — required only for `CoroutineSleeper`)

## Installation

```bash
composer require momo-framework/queue
```

## Core Concepts

### `#[AsJob]` — declare job parameters

```php
use Momo\Queue\Attributes\AsJob;

#[AsJob(maxAttempts: 5, priority: 10)]
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `maxAttempts` | `positive-int` | `3` | Total attempts including the initial run |
| `priority` | `int` | `0` | Higher value dispatches first |

### `AbstractJob` — the base class

Extend `Momo\Queue\Job\AbstractJob` and implement `handle(): void`. Optionally override `failed(Throwable $exception): void` for compensating actions after the retry budget is exhausted.

### `InMemoryQueue` — the built-in driver

Backed by two binary heaps. `push()` enqueues immediately; `later()` enqueues with a delay. `pop()` promotes due delayed jobs before returning the highest-priority ready envelope.

### Backoff policies

Three built-in policies implement `BackoffPolicyInterface`:

| Class | Behaviour |
|-------|-----------|
| `ExponentialBackoffPolicy` | `base * multiplier^(attempt-2)`, capped at `maxSeconds` |
| `ConstantBackoffPolicy` | Fixed delay for every retry |
| `FullJitterBackoffPolicy` | Random delay in `[0, exponential_delay]` |

Jobs may override the global policy per-class by implementing `HasBackoffPolicyInterface`.

### `Worker` — the processing loop

`Worker::run(WorkerOptions)` drains the queue until stopped, the job limit is reached, or memory is exceeded. `WorkerOptions::once()` is a convenience factory that processes a single job and exits.

### `CoroutineSleeper` — Swoole-aware idle wait

When `ext-swoole` is loaded, idle waits call `Swoole\Coroutine::sleep()` to yield the scheduler. Without Swoole it falls back to `usleep()`.

## Usage

### Defining a job

```php
use Momo\Queue\Attributes\AsJob;
use Momo\Queue\Job\AbstractJob;

#[AsJob(maxAttempts: 5, priority: 10)]
final class SendOrderReceipt extends AbstractJob
{
    public function __construct(
        private readonly string $orderId,
    ) {}

    public function handle(): void
    {
        // deliver the receipt
    }

    public function failed(\Throwable $e): void
    {
        // compensating action after retry budget is exhausted
        Log::critical("Receipt delivery failed for order {$this->orderId}", ['error' => $e->getMessage()]);
    }
}
```

### Dispatching jobs

```php
use Momo\Queue\Contracts\QueueInterface;

// Enqueue immediately with explicit priority
$queue->push(new SendOrderReceipt($orderId), priority: 10);

// Enqueue with a delay (seconds)
$queue->later(delaySeconds: 30, job: new SendOrderReceipt($orderId));

// Priority from the #[AsJob] attribute is used when not overriding here
$queue->push(new SendOrderReceipt($orderId));
```

### Running a worker

```bash
php momo queue:work             # Long-running worker
php momo queue:work --once      # Process a single job and exit
php momo queue:failed           # Inspect the dead-letter store
```

Or programmatically:

```php
use Momo\Queue\Worker\Worker;
use Momo\Queue\Worker\WorkerOptions;

$worker->run(new WorkerOptions(
    maxJobs: 0,           // 0 = unlimited
    sleepSeconds: 1,      // idle polling interval
    stopWhenEmpty: false, // keep running after queue drains
    memoryLimitMb: 256,   // restart when RSS exceeds this
));

// Process exactly one job and return:
$worker->run(WorkerOptions::once());
```

### Custom backoff per job

```php
use Momo\Queue\Backoff\ExponentialBackoffPolicy;
use Momo\Queue\Contracts\BackoffPolicyInterface;
use Momo\Queue\Contracts\HasBackoffPolicyInterface;
use Momo\Queue\Job\AbstractJob;

#[AsJob(maxAttempts: 10)]
final class ImportLargeFile extends AbstractJob implements HasBackoffPolicyInterface
{
    public function handle(): void { /* ... */ }

    public function backoffPolicy(): BackoffPolicyInterface
    {
        return new ExponentialBackoffPolicy(baseSeconds: 30, maxSeconds: 3600, multiplier: 2.0);
    }
}
```

### Lifecycle events

```php
use Momo\Events\Contracts\EventBusInterface;
use Momo\Queue\Events\JobFailed;
use Momo\Queue\Events\JobProcessed;
use Momo\Queue\Events\JobProcessing;

// Subscribe in a ServiceProvider::boot()
$bus->subscribe(JobProcessing::class, new LogJobStartedListener());
$bus->subscribe(JobProcessed::class,  new RecordMetricsListener());
$bus->subscribe(JobFailed::class,     new AlertOnFailureListener());
```

## Development

```bash
composer lint
composer stan
composer rector:check
composer test
composer ci
```
