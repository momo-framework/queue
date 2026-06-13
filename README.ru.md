# momo/queue

Корутино-безопасный движок очередей и воркеров с приоритетами задач и экспоненциальным backoff для высоконагруженного e-commerce.

> 🇬🇧 Documentation in English: [README.md](README.md)

## Установка

```bash
composer require momo-framework/queue
```

`QueueServiceProvider` обнаруживается автоматически. Он связывает
`QueueInterface`, `WorkerInterface`, `FailedJobStoreInterface`,
`BackoffPolicyInterface`, `ClockInterface` и `SleeperInterface`, а также
регистрирует консольные команды `queue:work` и `queue:failed`.

## Быстрый старт

Опишите задачу:

```php
use Momo\Queue\Attributes\AsJob;
use Momo\Queue\Job\AbstractJob;

#[AsJob(maxAttempts: 5, priority: 10)]
final class SendOrderReceipt extends AbstractJob
{
    public function __construct(private readonly string $orderId) {}

    public function handle(): void
    {
        // ... отправка чека ...
    }

    public function failed(\Throwable $e): void
    {
        // компенсирующее действие после исчерпания попыток
    }
}
```

Поставьте в очередь:

```php
$queue->push(new SendOrderReceipt($orderId), priority: 10);
$queue->later(30, new SendOrderReceipt($orderId)); // станет доступной через 30с
```

Запустите воркер:

```bash
php bin/console queue:work            # долгоживущий
php bin/console queue:work --once     # обработать одну задачу и выйти
php bin/console queue:failed          # просмотр dead-letter хранилища
```

## Как это работает

- **Приоритеты и задержки** — `pop()` возвращает *готовый* конверт с наивысшим
  приоритетом; при равенстве сохраняется порядок FIFO. Отложенные задачи никогда
  не всплывают раньше срока.
- **Повторы** — брошенная задача повторяется до `maxAttempts()`, каждый повтор
  планируется через `BackoffPolicyInterface` (по умолчанию — экспоненциальный).
- **Dead-letter** — исчерпавшие попытки задачи попадают в
  `FailedJobStoreInterface`, публикуется доменное событие `JobFailed`.
- **События жизненного цикла** — `JobProcessing`, `JobProcessed`, `JobFailed`
  публикуются в шину событий Momo для телеметрии и нотификаций.
- **Без блокировок** — воркер ожидает простой через `SleeperInterface`
  (учитывает корутины Swoole); обработчики не должны вызывать `sleep()`.

## Конфигурация

См. [`config/queue.php`](config/queue.php) и
[docs/ru/configuration.md](docs/ru/configuration.md).

## Документация

- [Обзор](docs/ru/overview.md)
- [Справочник API](docs/ru/api.md)
- [Конфигурация](docs/ru/configuration.md)

## Лицензия

AGPL-3.0-or-later.
