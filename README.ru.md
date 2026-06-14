<div align="center">
  <img src="https://avatars.githubusercontent.com/u/255415480?s=200&v=4" alt="Momo Framework" width="120" height="120" />

  <h1>momo-framework/queue</h1>

  <p>
      Корутино-совместимая приоритетная очередь с подключаемыми стратегиями backoff для <a href="https://github.com/momo-framework">Momo Framework</a>.
  </p>

  <p>
    <img src="https://github.com/momo-framework/queue/actions/workflows/ci.yml/badge.svg" alt="CI" />
    <img src="https://img.shields.io/packagist/v/momo-framework/queue.svg?style=flat" alt="Latest Version" />
    <img src="https://img.shields.io/packagist/dt/momo-framework/queue.svg?style=flat" alt="Total Downloads" />
    <img src="https://img.shields.io/badge/php-%3E%3D8.5-8892bf.svg" alt="PHP Version" />
    <img src="https://img.shields.io/badge/license-AGPL--3.0-blue.svg" alt="License" />
    <img src="https://img.shields.io/badge/coverage-100%25-brightgreen.svg" alt="Coverage" />
    <img src="https://img.shields.io/badge/PHPStan-level%2010-brightblue.svg" alt="PHPStan" />
  </p>
  <p>🇷🇺 Русская версия &nbsp;·&nbsp; 🇬🇧 <a href="README.md">English</a></p>
</div>

---

## Обзор

`momo-framework/queue` предоставляет корутино-совместимый движок очереди задач, основанный на приоритетном min-heap. Джобы объявляют бюджет повторных попыток и приоритет диспатча через единственный атрибут `#[AsJob]`. `Worker` непрерывно опустошает очередь, повторяя неудачи с настраиваемым backoff и перемещая исчерпавшие попытки джобы в хранилище упавших задач.

Пакет интегрируется с `momo-framework/events`: доменные события `JobProcessing`, `JobProcessed` и `JobFailed` публикуются на каждом переходе жизненного цикла, обеспечивая телеметрию и уведомления без привязки к воркеру.

`InMemoryQueue` — встроенный драйвер. Он использует два бинарных heap'а: один для готовых задач, упорядоченных по приоритету, и один для отложенных, упорядоченных по времени доступности. Безопасен в рамках кооперативного планировщика Swoole, так как каждая мутирующая операция завершается без точки приостановки I/O.

## Требования

- PHP >= 8.5
- `momo-framework/kernel`
- `momo-framework/events`
- `ext-swoole` (опционально — только для `CoroutineSleeper`)

## Установка

```bash
composer require momo-framework/queue
```

## Ключевые концепции

### `#[AsJob]` — параметры джоба

```php
use Momo\Queue\Attributes\AsJob;

#[AsJob(maxAttempts: 5, priority: 10)]
```

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `maxAttempts` | `positive-int` | `3` | Всего попыток, включая первый запуск |
| `priority` | `int` | `0` | Большее значение — более ранний диспатч |

### `AbstractJob` — базовый класс

Расширьте `Momo\Queue\Job\AbstractJob` и реализуйте `handle(): void`. Опционально переопределите `failed(Throwable $exception): void` для компенсирующих действий после исчерпания бюджета попыток.

### `InMemoryQueue` — встроенный драйвер

Основан на двух бинарных heap'ах. `push()` ставит в очередь немедленно; `later()` — с задержкой. `pop()` продвигает просроченные отложенные задачи перед возвратом envelope с наивысшим приоритетом.

### Стратегии backoff

Три встроенные политики реализуют `BackoffPolicyInterface`:

| Класс | Поведение |
|-------|-----------|
| `ExponentialBackoffPolicy` | `base * multiplier^(attempt-2)`, ограниченный `maxSeconds` |
| `ConstantBackoffPolicy` | Фиксированная задержка для каждой попытки |
| `FullJitterBackoffPolicy` | Случайная задержка в `[0, exponential_delay]` |

Джобы могут переопределить глобальную политику для своего класса, реализуя `HasBackoffPolicyInterface`.

### `Worker` — цикл обработки

`Worker::run(WorkerOptions)` опустошает очередь до остановки, достижения лимита задач или превышения памяти. `WorkerOptions::once()` — вспомогательная фабрика для обработки одной задачи и выхода.

### `CoroutineSleeper` — Swoole-совместимое ожидание

При загруженном `ext-swoole` простой использует `Swoole\Coroutine::sleep()` для передачи управления планировщику. Без Swoole откатывается на `usleep()`.

## Использование

### Определение джоба

```php
use Momo\Queue\Attributes\AsJob;
use Momo\Queue\Job\AbstractJob;

#[AsJob(maxAttempts: 5, priority: 10)]
final class SendOrderReceipt extends AbstractJob
{
    public function __construct(
        private readonly string $orderId,
    ) {}

    public function handle(): void
    {
        // доставить квитанцию
    }

    public function failed(\Throwable $e): void
    {
        // компенсирующее действие после исчерпания бюджета попыток
        Log::critical("Не удалось доставить квитанцию для заказа {$this->orderId}", ['error' => $e->getMessage()]);
    }
}
```

### Диспатч задач

```php
use Momo\Queue\Contracts\QueueInterface;

// Поставить в очередь немедленно с явным приоритетом
$queue->push(new SendOrderReceipt($orderId), priority: 10);

// Поставить в очередь с задержкой (в секундах)
$queue->later(delaySeconds: 30, job: new SendOrderReceipt($orderId));

// Используется приоритет из атрибута #[AsJob]
$queue->push(new SendOrderReceipt($orderId));
```

### Запуск воркера

```bash
php momo queue:work             # Долгоживущий воркер
php momo queue:work --once      # Обработать одну задачу и выйти
php momo queue:failed           # Инспекция хранилища упавших задач
```

Или программно:

```php
use Momo\Queue\Worker\Worker;
use Momo\Queue\Worker\WorkerOptions;

$worker->run(new WorkerOptions(
    maxJobs: 0,           // 0 = без ограничений
    sleepSeconds: 1,      // интервал простоя
    stopWhenEmpty: false, // продолжать после опустошения очереди
    memoryLimitMb: 256,   // перезапуск при превышении RSS
));

// Обработать ровно одну задачу и вернуться:
$worker->run(WorkerOptions::once());
```

### Кастомный backoff для отдельного джоба

```php
use Momo\Queue\Backoff\ExponentialBackoffPolicy;
use Momo\Queue\Contracts\BackoffPolicyInterface;
use Momo\Queue\Contracts\HasBackoffPolicyInterface;
use Momo\Queue\Job\AbstractJob;

#[AsJob(maxAttempts: 10)]
final class ImportLargeFile extends AbstractJob implements HasBackoffPolicyInterface
{
    public function handle(): void { /* ... */ }

    public function backoffPolicy(): BackoffPolicyInterface
    {
        return new ExponentialBackoffPolicy(baseSeconds: 30, maxSeconds: 3600, multiplier: 2.0);
    }
}
```

### События жизненного цикла

```php
use Momo\Events\Contracts\EventBusInterface;
use Momo\Queue\Events\JobFailed;
use Momo\Queue\Events\JobProcessed;
use Momo\Queue\Events\JobProcessing;

// Подписка в ServiceProvider::boot()
$bus->subscribe(JobProcessing::class, new LogJobStartedListener());
$bus->subscribe(JobProcessed::class,  new RecordMetricsListener());
$bus->subscribe(JobFailed::class,     new AlertOnFailureListener());
```

## Разработка

```bash
composer lint
composer stan
composer rector:check
composer test
composer ci
```
