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
  <b>Job queue for Momo on RabbitMQ: handlers per module, signed jobs, concurrent workers in coroutines.</b>
</div>

<div align="center">
  🇬🇧 English · <a href="README.ru.md">🇷🇺 Русский</a> · <a href="README.hy.md">🇦🇲 Հայերեն</a>
</div>

---

## Features

- A job is a plain object of values, its handler a `JobHandler` with `__invoke(SendInvoice $job)` in `Presentation/Queue/` of a module, found by the job type
- `#[Job(queue: 'mail', attempts: 5, backoff: [10, 60, 300])]` sets queue, attempts and retry delays; the default backoff is exponential with full jitter
- RabbitMQ only: durable queues, persistent messages, a delay is a TTL queue that dead-letters back into its queue, failed jobs wait in `momo.failed`
- Every job is signed with `APP_KEY`: a payload changed in the broker or signed by another app is never opened and goes straight to the failed jobs
- A job whose worker died returns to its queue when the broker sees the connection gone
- `queue:work --concurrency=20`: twenty jobs at once in one process, SIGTERM lets them finish
- A broker that stays down for `--max-failures` polls in a row (12 by default, about a minute) ends `queue:work` with code 1, so docker or the supervisor starts a fresh process instead of keeping a worker on a dead link
- Failed jobs are kept: `queue:failed`, `queue:retry <id>|all`; `queue:list` shows every job with its handler, queue and attempts
- A `JobObserver` in any module sees every run of `queue:work`: done, retried or failed, with the error and the milliseconds. A history of jobs, metrics or an alert is one class; an observer that throws stops no job
- The `memory` transport for tests: `queue.transport = memory` keeps jobs in the process
- A command of the bus with `#[Queued(queue: 'reports', attempts: 3)]` goes to the queue after the permission check, the sender gets `null`; the worker sends it to the same bus as the user and team who sent it, so the permission, the transaction and the events after commit run there again. Every `Momo\Bus\Contracts\Context` of the app carries its state with the command

## Why

A worker process that runs one job at a time idles while each job waits on HTTP or SQL. With coroutines the same process keeps twenty jobs in flight on one CPU. The handler sits next to the controllers because a queue is one more way into a module, and the job stays a value object so the code that dispatches it never depends on the code that runs it.

## Quick start

```bash
composer require anidzen/momo-queue
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

- `QUEUE_NAME`, default `default`: the queue of a job without `#[Job(queue:)]`
- `QUEUE_RETRY_AFTER`, default 90: seconds a taken job may run
- `QUEUE_FAILED`, default `momo.failed`: the queue of failed jobs
- `AMQP_HOST`, `AMQP_PORT`, `AMQP_USER`, `AMQP_PASSWORD`, `AMQP_VHOST`: where RabbitMQ is, the password is a secret
- `AMQP_HEARTBEAT` (30), `AMQP_TIMEOUT` (5.0), `AMQP_POOL` (32): heartbeat and timeouts of a connection, connections per worker

## Development

```bash
composer install
composer check
```

Tests against RabbitMQ run when `AMQP_HOST` is set: `docker run -d -p 5672:5672 rabbitmq:4-alpine`, then `AMQP_HOST=127.0.0.1 composer test`.

## Status

0.6. Public API: `Contracts\Queue`, `Contracts\JobHandler`, `Contracts\Transport`, `Job`, `Envelope`, `FailedJob`, `Contracts\JobObserver`, `JobRun`, `RunStatus`, `Worker`, `WorkerOptions`.

## License

[AGPL-3.0-or-later](LICENSE).

---

<div align="center">
  <sub>© 2024 - 2026 Anidzen Labs. Innovation through simplicity.</sub><br/>
  <sub>Built by <a href="https://vahe-sargsyan.com/">w33bvGL</a></sub>
</div>
