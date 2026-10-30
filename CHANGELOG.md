# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
