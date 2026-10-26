<div align="center">
  <img src="https://gitlab.com/anidzen/design-assets/-/raw/main/anidzen/TRANSPARENT_WHITE_RING.png" height="144px" alt="Anidzen Labs">
  <h1>
    anidzen/momo-queue
  </h1>
</div>

<div align="center">

  <a href="https://gitlab.com/anidzen/momo/packages/queue/-/pipelines">
    <img src="https://gitlab.com/anidzen/momo/packages/queue/badges/main/pipeline.svg" alt="CI/CD"/>
  </a>
  <img src="https://img.shields.io/badge/PHP-8.5+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.5+"/>
  <img src="https://img.shields.io/badge/License-AGPL--3.0-lightgrey?style=flat-square" alt="License"/>

</div>

<div align="center">
  <b>Job queue for Momo with Redis and database transports.</b><br/>
  Handlers live in each module's Presentation/Queue, one worker runs many jobs at once in coroutines.
</div>

<div align="center">
  🇬🇧 English · <a href="README.ru.md">🇷🇺 Русский</a> · <a href="README.hy.md">🇦🇲 Հայերեն</a>
</div>

---

## Features

- A job is a plain object of values, its handler a `JobHandler` with `__invoke(SendInvoice $job)` in `Presentation/Queue/` of a module, found by the job type
- `#[Job(queue: 'mail', attempts: 5, backoff: [10, 60, 300])]` sets queue, attempts and retry delays; the default backoff is exponential with full jitter
- `redis` transport: ready list, delayed and reserved sorted sets, every step one Lua script, a job of a dead worker returns after `retry_after` seconds
- `database` transport: a job dispatched inside a transaction commits with your data; workers take jobs with `FOR UPDATE SKIP LOCKED`
- `sync` runs the handler on dispatch, `memory` keeps jobs in the process for tests
- `queue:work --concurrency=20`: twenty jobs at once in one process, SIGTERM lets them finish
- Failed jobs are kept: `queue:failed`, `queue:retry <id>|all`
- `queue:list` shows every job with its handler, queue and attempts
- `make:job Billing SendInvoice` writes the job and its handler

## Why

A worker process that runs one job at a time idles while each job waits on HTTP or SQL. With coroutines the same process keeps twenty jobs in flight on one CPU. The handler sits next to the controllers because a queue is one more way into a module, and the job stays a value object so the code that dispatches it never depends on the code that runs it.

## Quick start

```bash
composer require anidzen/momo-queue anidzen/momo-redis
momo make:job Billing SendInvoice
```

```dotenv
QUEUE_CONNECTION=redis
```

```php
namespace App\Billing\Application;

use Momo\Queue\Job;

#[Job(queue: 'mail', attempts: 5)]
final readonly class SendInvoice
{
    public function __construct(public int $invoiceId) {}
}
```

```php
namespace App\Billing\Presentation\Queue;

use App\Billing\Application\SendInvoice;
use App\Billing\Application\InvoiceMailer;
use Momo\Queue\Contracts\JobHandler;

final readonly class SendInvoiceHandler implements JobHandler
{
    public function __construct(private InvoiceMailer $mailer) {}

    public function __invoke(SendInvoice $job): void
    {
        $this->mailer->send($job->invoiceId);
    }
}
```

```php
$this->queue->dispatch(new SendInvoice($invoice->id));
$this->queue->dispatch(new SendInvoice($invoice->id), delay: 3600);
```

```bash
momo queue:work --queue=mail,default --concurrency=20
```

## Configuration

- `QUEUE_CONNECTION`, default `sync`: `sync`, `memory`, `redis` or `database`
- `QUEUE_REDIS_CONNECTION`, default `default`: a connection of `config/redis.php`
- `QUEUE_DB_CONNECTION`: a connection of `config/database.php`, the default one when empty
- `config/queue.php`: `queue` (default queue name), `retry_after` (seconds a taken job stays hidden)

The `database` transport brings its `jobs` and `failed_jobs` tables; `momo migrate` creates them.

## Development

```bash
composer install --ignore-platform-req=ext-redis
composer check
```

Tests against Redis run when `REDIS_HOST` is set, the database transport is tested on SQLite.

## Status

0.2. Public API: `Contracts\Queue`, `Contracts\JobHandler`, `Contracts\Transport`, `Job`, `Envelope`, `FailedJob`, `Worker`, `WorkerOptions`.

## License

[AGPL-3.0-or-later](LICENSE).

---

<div align="center">
  <sub>© 2024 - 2026 Anidzen Labs. Innovation through simplicity.</sub><br/>
  <sub>Built by <a href="https://vahe-sargsyan.com/">w33bvGL</a></sub>
</div>
