# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.6.2] - 2026-11-19

### Fixed

- The backoff of `Job` passes phpstan 2.3.6 and newer, which stopped the analyse step and the publish of 0.6.1. The delays stay the same. 0.6.1 never reached the registry, so 0.6.2 is the first release with `JobObserver`.

## [0.6.1] - 2026-11-19

### Added

- `Contracts\JobObserver`: `queue:work` hands it every run of a job as a `JobRun` with its `RunStatus` (`processed`, `retried`, `failed`), the envelope, the error and the milliseconds. An app keeps a history of jobs or counts them with one class in `Infrastructure/`, no worker of its own. An observer that throws is shown as `not observed` and stops no job.

## [0.6.0] - 2026-11-14

### Fixed

- `queue:work` no longer runs forever on a broker that stays down. After `--max-failures` failed polls in a row (12 by default, about a minute with the backoff) it exits with code 1, so docker or the supervisor starts a fresh process. `0` keeps the old endless retry.

### Changed

- README in en, ru and hy describes the RabbitMQ transport, its keys and the signed jobs. The `redis`, `database` and `sync` transports and `make:job` it still listed left the package earlier.

### Removed

- `anidzen/momo-amqp` is archived. It repeated `Momo\Queue\Amqp` of this package and no package required it. An app that still lists it in `composer.json` drops the line, nothing else changes.

## [0.5.2] - 2026-11-10

### Changed

- The checks for a PHP without Swoole are gone: Momo always runs on Swoole, and the package now says so in `require` with `ext-swoole`.
- The worker stops on SIGTERM and SIGINT through Swoole only. The pcntl fallback for a PHP without Swoole is gone.

## [0.5.1] - 2026-11-10

### Changed

- The health check implements `Momo\Kernel\Health\Check` from the kernel. The package no longer requires the `momo-health` module and conflicts with its releases before 0.2, which look for the old interface.
- Requires `momo-kernel` ^0.5.13.

## [0.5.0] - 2026-11-10

### Changed

- A queued job is signed with a key derived from `APP_KEY` when it is pushed, and the worker checks the signature before it opens the job. A job that the app did not sign goes to the failed list at once, unopened and without retries. Before, anyone able to publish to RabbitMQ could make the worker build objects of their choice.
- `Envelope::wrap()`, `Envelope::job()`, `Dispatcher` and `Worker` take the signer; in an app the container passes it (`QueueServiceProvider::SIGNER`).
- Jobs pushed by 0.4 carry no signature: let the workers empty the queues before the update, or retry what lands in the failed list after it by pushing the jobs again.
- Requires `momo-kernel` ^0.5.13.

### Added

- `Envelope::fresh()`: the same job with its attempts reset, used by `queue:retry`.

## [0.4.0] - 2026-11-07

### Changed

- The queue runs on RabbitMQ only. The AMQP transport of `momo-amqp` lives here now (`Momo\Queue\Amqp`), with its readiness check, and every app sends jobs and `#[Queued]` commands there. Connection keys stay `AMQP_HOST`, `AMQP_PORT`, `AMQP_USER`, `AMQP_PASSWORD`, `AMQP_VHOST`; `AMQP_PASSWORD` must be a secret of its own in production.
- Requires `momo-bus`, `momo-health`, `momo-server` and `momo-console` 0.3: they come with every Momo app.
- The config file is in the Momo config layout, checked by `momo config:check`.
- No prose comments in the code: names say what it does, docblocks keep only the types PHPStan reads.

### Fixed

- A push returns only after RabbitMQ confirmed the message (publisher confirms), and a refused message throws. Before, a push returned while the message could still be lost with its connection.

### Removed

- `QUEUE_CONNECTION` and the `sync`, `redis` and `database` transports with the `jobs` tables. Tests swap the transport for `MemoryTransport` (`queue.transport` = `memory`).
- `anidzen/momo-amqp`: drop it from composer.json.

## [0.3.1] - 2026-11-06

### Dependencies

- Requires `momo-kernel` 0.5, `momo-database` 0.3 for development. The ranges that still let in older releases are gone.

## [0.3.0] - 2026-11-05

### Added

- Commands of `momo-bus` 0.3.3 with `#[Queued]` run in a worker: the bus of the sender checks the permission and puts the command in the queue, the worker sends it back to the bus within every `Context` of the app (the user, the team), so the whole chain runs again there. Queue, attempts and backoff come from `#[Queued]`. Without a booted bus the queue works with jobs only.

### Changed

- Accepts `momo-database` 0.3.

## [0.2.5] - 2026-11-05

### Changed

- Accepts `momo-kernel` 0.5.

## [0.2.4] - 2026-11-04

### Changed

- Environment variables of the config are read with `Env` of `momo-kernel` 0.4.12: wrong values, out of range numbers and unknown options are reported together at boot and by `env:check`, and `.env` needs no comment to say what a key takes.

## [0.2.3] - 2026-10-30

### Added

- A transport can come from another package: `QUEUE_CONNECTION=amqp` with `anidzen/momo-amqp` installed. Without such a package every queue call fails with the name of the package to install, instead of failing the boot.

## [0.2.2] - 2026-10-29

### Fixed

- A job that ran but whose ack was lost to a broker blip counted as failed and ran again at once. The ack now sits outside the job's error handling: the job comes back only after its visibility timeout, as at-least-once delivery allows.
- A broker restart killed the coroutine that hit it: `queue:work --concurrency 20` lost a coroutine on every error and finally exited. Transport errors are reported as `queue unreachable`, and the loop polls again after a pause that doubles up to 5 seconds.

### Changed

- The report closure of `Worker` receives `null` for the envelope with the status `error`.
- Requires `anidzen/momo-console` ^0.2.

## [0.2.1] - 2026-10-26

### Added

- `Application/` may also use the `#[Job]` attribute, so a job made by `make:job` passes `arch:check`.

## [0.2.0] - 2026-10-26

### Added

- Rewritten on kernel 0.4. A job is a value object, its handler a `Contracts\JobHandler` found by the type of `__invoke`; the package declares the `Queue` protocol, so handlers live in `Presentation/Queue/` of a module.
- `#[Job]`: queue, attempts and retry delays per job class; default backoff is exponential with full jitter.
- Transports: `redis` (Lua scripts over a list and two sorted sets, visibility timeout), `database` (jobs commit with the caller's transaction, `FOR UPDATE SKIP LOCKED`, tables shipped as a migration), `memory` for tests, `sync` runs on dispatch.
- `Worker` runs `--concurrency` jobs at once in coroutines and stops gracefully on SIGTERM and SIGINT.
- `queue:work`, `queue:failed`, `queue:retry`, `queue:list`.
- `extra.momo.layers`: `Application/` may use `Momo\Queue\Contracts\`.
- README in English, Russian and Armenian.

### Removed

- `AbstractJob`, `JobInterface`, the in-memory priority queue, backoff policy classes, sleepers, clock and the events the worker published.
- `docs/`: the README covers the package.

## [0.1.0] - 2026-10-22

### Added

- First release in the Anidzen Composer registry as `anidzen/momo-queue`: Coroutine-aware Queue/Worker engine with priorities and exponential backoff for Momo Framework.
