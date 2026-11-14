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
  <b>Очередь задач для Momo на RabbitMQ: обработчики в модулях, подписанные задачи, параллельные воркеры на корутинах.</b>
</div>

<div align="center">
  <a href="README.md">🇬🇧 English</a> · 🇷🇺 Русский · <a href="README.hy.md">🇦🇲 Հայերեն</a>
</div>

---

## Возможности

- Задача это простой объект значений, её обработчик это `JobHandler` с `__invoke(SendInvoice $job)` в `Presentation/Queue/` модуля, находится по типу задачи
- `#[Job(queue: 'mail', attempts: 5, backoff: [10, 60, 300])]` задаёт очередь, число попыток и задержки повтора; по умолчанию экспоненциальная задержка с полным джиттером
- Только RabbitMQ: стойкие очереди, сохраняемые сообщения, задержка это очередь с TTL, которая возвращает сообщение в свою очередь, упавшие задачи ждут в `momo.failed`
- Каждая задача подписана `APP_KEY`: тело, изменённое в брокере или подписанное другим приложением, не открывается и сразу уходит в упавшие
- Задача умершего воркера возвращается в очередь, когда брокер видит, что соединение пропало
- `queue:work --concurrency=20`: двадцать задач одновременно в одном процессе, SIGTERM даёт им доработать
- Если брокер недоступен `--max-failures` опросов подряд (12 по умолчанию, около минуты), `queue:work` выходит с кодом 1, и docker или супервизор запускает свежий процесс, а не держит воркер на мёртвом соединении
- Упавшие задачи хранятся: `queue:failed`, `queue:retry <id>|all`; `queue:list` показывает каждую задачу с обработчиком, очередью и попытками
- Транспорт `memory` для тестов: `queue.transport = memory` держит задачи в процессе
- Команда шины с `#[Queued(queue: 'reports', attempts: 3)]` уходит в очередь после проверки прав, отправитель получает `null`; воркер отдаёт её той же шине от имени того же пользователя и команды, поэтому права, транзакция и события после коммита срабатывают там заново. Каждый `Momo\Bus\Contracts\Context` приложения переносит своё состояние вместе с командой

## Зачем

Воркер, который выполняет одну задачу за раз, простаивает, пока задача ждёт HTTP или SQL. С корутинами тот же процесс держит двадцать задач на одном CPU. Обработчик лежит рядом с контроллерами, потому что очередь это ещё один вход в модуль, а задача остаётся объектом-значением, и код, который её ставит, не зависит от кода, который её выполняет.

## Быстрый старт

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

## Настройка

- `QUEUE_NAME`, по умолчанию `default`: очередь задачи без `#[Job(queue:)]`
- `QUEUE_RETRY_AFTER`, по умолчанию 90: сколько секунд может идти взятая задача
- `QUEUE_FAILED`, по умолчанию `momo.failed`: очередь упавших задач
- `AMQP_HOST`, `AMQP_PORT`, `AMQP_USER`, `AMQP_PASSWORD`, `AMQP_VHOST`: где RabbitMQ, пароль это секрет
- `AMQP_HEARTBEAT` (30), `AMQP_TIMEOUT` (5.0), `AMQP_POOL` (32): heartbeat и таймауты соединения, соединений на воркер

## Разработка

```bash
composer install
composer check
```

Тесты на RabbitMQ идут, когда задан `AMQP_HOST`: `docker run -d -p 5672:5672 rabbitmq:4-alpine`, затем `AMQP_HOST=127.0.0.1 composer test`.

## Статус

0.6. Публичный API: `Contracts\Queue`, `Contracts\JobHandler`, `Contracts\Transport`, `Job`, `Envelope`, `FailedJob`, `Worker`, `WorkerOptions`.

## Лицензия

[AGPL-3.0-or-later](LICENSE).

---

<div align="center">
  <sub>© 2024 - 2026 Anidzen Labs. Innovation through simplicity.</sub><br/>
  <sub>Built by <a href="https://vahe-sargsyan.com/">w33bvGL</a></sub>
</div>
