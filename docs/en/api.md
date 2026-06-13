# API Reference

## Contracts

### `JobInterface`
- `handle(): void` — execute the work; throwing triggers a retry.
- `failed(\Throwable $e): void` — called once after the final attempt fails.
- `maxAttempts(): positive-int` — initial run plus retries.

### `QueueInterface`
- `push(JobInterface $job, int $priority = 0): non-empty-string`
- `later(int $delaySeconds, JobInterface $job, int $priority = 0): non-empty-string`
- `pop(): ?Envelope` — next *ready* envelope (priority order), or null.
- `release(Envelope $envelope, int $delaySeconds): void` — re-enqueue for retry.
- `size(): int<0, max>` — held envelopes, including not-yet-available ones.

### `WorkerInterface`
- `run(WorkerOptions $options): void` — processing loop.
- `runOnce(): bool` — process at most one ready envelope; never blocks.
- `stop(): void` — request graceful shutdown (signal-safe).

### `BackoffPolicyInterface`
- `delayFor(positive-int $attempt): int<0, max>` — seconds before `$attempt`.

### `FailedJobStoreInterface`
- `store(Envelope $e, \Throwable $ex): non-empty-string`
- `all(): list<FailedJobRecord>` — most recent first.
- `forget(non-empty-string $id): bool`
- `flush(): int<0, max>`

### `ClockInterface` / `SleeperInterface` / `HasBackoffPolicyInterface`
- `ClockInterface::now(): int<0, max>` — injected time source.
- `SleeperInterface::sleep(int|float $seconds): void` — coroutine-aware idle wait.
- `HasBackoffPolicyInterface::backoffPolicy(): BackoffPolicyInterface` — per-job override.

## Implementations

### `Job\AbstractJob`
Base class: reads `#[AsJob(maxAttempts, priority)]` for defaults, supplies a
no-op `failed()`, exposes `priority(): int`. Throws `InvalidJobException` if a
non-positive `maxAttempts` is declared.

### `Queue\Envelope` *(final readonly)*
Fields: `id`, `job`, `priority`, `availableAt`, `attempts`, `sequence`.
Methods: `isReady(int $now): bool`, `withAttempts()`, `withAvailableAt()`,
`withSequence()` — all return new copies.

### `Queue\PriorityQueue`
Binary heap over a `callable(Envelope, Envelope): int` comparator.
`insert()`, `extract(): ?Envelope`, `peek(): ?Envelope`, `size()`, `isEmpty()`.
Deterministic PHP fallback for the native min-heap.

### `Queue\Comparators`
`byPriority()` (highest first, FIFO tie-break) and `byAvailability()` (earliest
first) factory closures.

### `Queue\InMemoryQueue`
`QueueInterface` backed by a ready heap + a delayed heap, ordered by a
`ClockInterface`.

### `Backoff\ExponentialBackoffPolicy`
`base * multiplier^(attempt-2)`, clamped to `maxSeconds`. First retry waits
`base`. Validates arguments in the constructor.

### `Backoff\ConstantBackoffPolicy`
Fixed delay for every retry.

### `Backoff\FullJitterBackoffPolicy`
Decorator returning a random delay in `[0, inner.delayFor()]`; accepts an
injectable randomizer for deterministic tests.

### `Worker\Worker`
Implements `WorkerInterface`. Publishes `JobProcessing` / `JobProcessed` /
`JobFailed`. A throwing `failed()` handler can never crash the loop.

### `Worker\WorkerOptions` *(final readonly)*
`maxJobs`, `sleepSeconds`, `stopWhenEmpty`, `memoryLimitMb`; `WorkerOptions::once()`.

### `Worker\CoroutineSleeper` / `Worker\NullSleeper`
Swoole-coroutine idle wait; and a no-op for tests.

### `FailedJob\InMemoryFailedJobStore`
Process-local dead-letter store.

### `Support\SystemClock` / `Support\MutableClock` / `Support\FailedJobRecord`
System time; hand-advanced test clock; immutable failure record
(`id`, `jobClass`, `exceptionClass`, `message`, `attempts`, `failedAt`).

## Events (`Momo\Queue\Events`)
`JobProcessing`, `JobProcessed`, `JobFailed` — all extend `Momo\Events\DomainEvent`
and carry the `Envelope` (and the `\Throwable` for `JobFailed`).

## Console
- `queue:work` — `--once`, `--max-jobs`, `--sleep`, `--memory`.
- `queue:failed` — tabular dead-letter listing.
