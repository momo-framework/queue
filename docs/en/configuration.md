# Configuration

Package defaults live in `config/queue.php` and are merged under the `queue`
key. Publish and override them in your application's `config/queue.php`.

## `backoff` — default retry cadence

Applied to retried jobs that do **not** implement `HasBackoffPolicyInterface`.

| Key            | Type    | Default | Meaning                                            |
|----------------|---------|---------|----------------------------------------------------|
| `base_seconds` | `int≥1` | `5`     | Delay before the first retry.                      |
| `max_seconds`  | `int≥base` | `3600` | Ceiling no computed delay may exceed.           |
| `multiplier`   | `float≥1.0` | `2.0` | Growth factor applied on each subsequent retry.  |

Delay for attempt *n* (n≥2): `min(max_seconds, base_seconds * multiplier^(n-2))`.
With the defaults: retry delays are `5, 10, 20, 40, …` seconds, capped at one hour.

Invalid values are coerced to safe bounds by `QueueServiceProvider`
(`base ≥ 1`, `max ≥ base`, `multiplier ≥ 1.0`).

## `worker` — default runtime limits

Consumed by `queue:work` flags (CLI flags win over config).

| Key               | Type    | Default | Meaning                                                   |
|-------------------|---------|---------|-----------------------------------------------------------|
| `sleep_seconds`   | `int≥0` | `1`     | Idle wait when nothing is ready (`--sleep`).              |
| `memory_limit_mb` | `int≥0` | `256`   | Worker exits when RSS exceeds this; `0` disables (`--memory`). |
| `max_jobs`        | `int≥0` | `0`     | Process this many jobs then exit; `0` = unlimited (`--max-jobs`). |

## Console flags

```bash
php bin/console queue:work --once            # maxJobs=1, stopWhenEmpty=true
php bin/console queue:work --max-jobs=1000   # recycle the worker after 1000 jobs
php bin/console queue:work --sleep=2         # idle poll every 2s
php bin/console queue:work --memory=512      # restart threshold 512 MB
```

## Swappable bindings

All bindings are interface-keyed, so an application provider may rebind any of
them before boot:

| Interface                  | Default                     |
|----------------------------|-----------------------------|
| `QueueInterface`           | `InMemoryQueue`             |
| `FailedJobStoreInterface`  | `InMemoryFailedJobStore`    |
| `BackoffPolicyInterface`   | `ExponentialBackoffPolicy`  |
| `SleeperInterface`         | `CoroutineSleeper`          |
| `ClockInterface`           | `SystemClock`               |

For cross-worker durability replace `QueueInterface` and
`FailedJobStoreInterface` with persistent (Redis/DB) implementations.
