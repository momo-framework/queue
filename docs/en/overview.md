# Overview

`momo/queue` is the deferred-work layer of Momo. It exists so that latency-
sensitive request handlers (checkout, cart, payment callbacks) can offload slow
or unreliable work — sending mail, calling a payment gateway, reindexing — to a
background worker instead of blocking the response on the 45k+ RPS hot path.

## Where it sits

```
HTTP / Command handler ──push()──▶ QueueInterface ──pop()──▶ Worker ──▶ handle()
                                          │                     │
                                     (priority +           on failure
                                      delay heaps)         └─ release() with backoff
                                                           └─ FailedJobStore + JobFailed
```

The package depends only on `momo/kernel` (service provider base), `momo/console`
(the worker commands) and `momo/events` (lifecycle events). It pulls in neither
the database nor Swoole directly: the Swoole-aware idle wait is isolated behind
`SleeperInterface`, keeping the engine testable and framework-layer-clean.

## Core building blocks

| Concept            | Type                          | Responsibility                                   |
|--------------------|-------------------------------|--------------------------------------------------|
| Job                | `JobInterface` / `AbstractJob`| the unit of work + retry budget + `failed()` hook|
| Envelope           | `Envelope`                    | immutable wrapper: id, priority, availability, attempts |
| Queue              | `QueueInterface` / `InMemoryQueue` | priority- and delay-ordered storage         |
| Heap               | `PriorityQueue`               | binary heap; PHP fallback for the native min-heap|
| Backoff            | `BackoffPolicyInterface`      | retry delay strategy (exponential / constant / jitter) |
| Worker             | `WorkerInterface` / `Worker`  | drains the queue, retries, dead-letters          |
| Failed store       | `FailedJobStoreInterface`     | dead-letter records for exhausted jobs           |

## Design decisions

- **Two heaps, not one.** Ready jobs are ordered by priority; delayed jobs by
  availability time. `pop()` promotes any newly-due delayed job into the ready
  heap, then extracts. This keeps both "highest priority first" and "never run
  early" correct without scanning.
- **Clock injection.** Every time read goes through `ClockInterface`, so delay
  and backoff behaviour is deterministic under test (`MutableClock`).
- **Immutable envelopes.** Retry handling rebuilds an envelope with incremented
  attempts rather than mutating shared state — safe across coroutines.
- **Backoff as a strategy.** A job may override the worker default by
  implementing `HasBackoffPolicyInterface`.

## Memory & coroutine safety

`InMemoryQueue` and `InMemoryFailedJobStore` hold state for the worker's
lifetime, never per request, so there is no cross-request accumulation. Their
mutating methods contain no I/O suspension point, so under Swoole's cooperative
scheduler they are atomic with respect to sibling coroutines in the same worker.
The `Worker` enforces a memory ceiling (`WorkerOptions::$memoryLimitMb`) and
exits cleanly when exceeded, letting a runtime guardian respawn a fresh worker —
the back-pressure hand-off point against slow leaks.

For cross-worker fan-out, bind a Redis- or database-backed `QueueInterface`
implementation; the contracts are designed for it.
