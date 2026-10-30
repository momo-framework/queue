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
  <b>Очередь задач для Momo: транспорты Redis и база, обработчики по модулям, параллельные воркеры на корутинах.</b><br/>
  Обработчики лежат в Presentation/Queue модуля, один воркер выполняет много задач сразу в корутинах.
</div>

<div align="center">
  <a href="README.md">🇬🇧 English</a> · 🇷🇺 Русский · <a href="README.hy.md">🇦🇲 Հայերեն</a>
</div>

---

## Возможности

- Задача это объект со значениями, её обработчик это `JobHandler` с `__invoke(SendInvoice $job)` в `Presentation/Queue/` модуля, он находится по типу задачи
- `#[Job(queue: 'mail', attempts: 5, backoff: [10, 60, 300])]` задаёт очередь, число попыток и паузы между ними; по умолчанию экспонента с полным jitter
- Транспорт `redis`: список готовых задач, отложенные и занятые в sorted set, каждый шаг одним Lua-скриптом, задача упавшего воркера возвращается через `retry_after` секунд
- Транспорт `database`: задача, поставленная внутри транзакции, коммитится вместе с данными; воркеры берут задачи через `FOR UPDATE SKIP LOCKED`
- `sync` выполняет обработчик сразу при постановке, `memory` держит задачи в процессе для тестов
- `queue:work --concurrency=20`: двадцать задач сразу в одном процессе, по SIGTERM текущие доделываются
- Упавшие задачи сохраняются: `queue:failed`, `queue:retry <id>|all`
- `queue:list` показывает все задачи с обработчиком, очередью и числом попыток
- `make:job Billing SendInvoice` создаёт задачу и её обработчик

## Зачем

Воркер, который выполняет одну задачу за раз, простаивает, пока задача ждёт HTTP или SQL. С корутинами тот же процесс держит двадцать задач на одном CPU. Обработчик лежит рядом с контроллерами, потому что очередь это ещё один вход в модуль, а задача остаётся объектом-значением, и код, который её ставит, не зависит от кода, который её выполняет.

## Быстрый старт

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

## Настройка

- `QUEUE_CONNECTION`, по умолчанию `sync`: `sync`, `memory`, `redis` или `database`
- `QUEUE_REDIS_CONNECTION`, по умолчанию `default`: соединение из `config/redis.php`
- `QUEUE_DB_CONNECTION`: соединение из `config/database.php`, если пусто, основное
- `config/queue.php`: `queue` (очередь по умолчанию), `retry_after` (сколько секунд взятая задача скрыта)

Транспорт `database` приносит таблицы `jobs` и `failed_jobs`, их создаёт `momo migrate`.

## Разработка

```bash
composer install --ignore-platform-req=ext-redis
composer check
```

Тесты на Redis запускаются, если задан `REDIS_HOST`, транспорт базы проверяется на SQLite.

## Статус

0.2. Публичный API: `Contracts\Queue`, `Contracts\JobHandler`, `Contracts\Transport`, `Job`, `Envelope`, `FailedJob`, `Worker`, `WorkerOptions`.

## Лицензия

[AGPL-3.0-or-later](LICENSE).

---

<div align="center">
  <sub>© 2024 - 2026 Anidzen Labs. Innovation through simplicity.</sub><br/>
  <sub>Built by <a href="https://vahe-sargsyan.com/">w33bvGL</a></sub>
</div>
