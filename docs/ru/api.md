# Справочник API

## Контракты

### `JobInterface`
- `handle(): void` — выполнить работу; исключение запускает повтор.
- `failed(\Throwable $e): void` — вызывается один раз после провала последней попытки.
- `maxAttempts(): positive-int` — первый запуск плюс повторы.

### `QueueInterface`
- `push(JobInterface $job, int $priority = 0): non-empty-string`
- `later(int $delaySeconds, JobInterface $job, int $priority = 0): non-empty-string`
- `pop(): ?Envelope` — следующий *готовый* конверт (по приоритету) или null.
- `release(Envelope $envelope, int $delaySeconds): void` — повторная постановка.
- `size(): int<0, max>` — хранимые конверты, включая ещё недоступные.

### `WorkerInterface`
- `run(WorkerOptions $options): void` — цикл обработки.
- `runOnce(): bool` — обработать максимум один готовый конверт; не блокирует.
- `stop(): void` — запросить корректное завершение (безопасно из сигнала).

### `BackoffPolicyInterface`
- `delayFor(positive-int $attempt): int<0, max>` — секунды до `$attempt`.

### `FailedJobStoreInterface`
- `store(Envelope $e, \Throwable $ex): non-empty-string`
- `all(): list<FailedJobRecord>` — сначала самые свежие.
- `forget(non-empty-string $id): bool`
- `flush(): int<0, max>`

### `ClockInterface` / `SleeperInterface` / `HasBackoffPolicyInterface`
- `ClockInterface::now(): int<0, max>` — источник времени.
- `SleeperInterface::sleep(int|float $seconds): void` — учитывающее корутины ожидание.
- `HasBackoffPolicyInterface::backoffPolicy(): BackoffPolicyInterface` — переопределение для задачи.

## Реализации

### `Job\AbstractJob`
Базовый класс: читает `#[AsJob(maxAttempts, priority)]` для дефолтов, даёт
no-op `failed()`, предоставляет `priority(): int`. Бросает `InvalidJobException`
при неположительном `maxAttempts`.

### `Queue\Envelope` *(final readonly)*
Поля: `id`, `job`, `priority`, `availableAt`, `attempts`, `sequence`.
Методы: `isReady(int $now): bool`, `withAttempts()`, `withAvailableAt()`,
`withSequence()` — все возвращают новые копии.

### `Queue\PriorityQueue`
Бинарная куча по компаратору `callable(Envelope, Envelope): int`.
`insert()`, `extract(): ?Envelope`, `peek(): ?Envelope`, `size()`, `isEmpty()`.
Детерминируемый PHP-fallback для нативной min-heap.

### `Queue\Comparators`
Фабрики замыканий `byPriority()` (сначала наивысший, FIFO при равенстве) и
`byAvailability()` (сначала ранний).

### `Queue\InMemoryQueue`
`QueueInterface` на готовой и отложенной кучах, упорядоченных через `ClockInterface`.

### `Backoff\ExponentialBackoffPolicy`
`base * multiplier^(attempt-2)`, ограничено `maxSeconds`. Первый повтор ждёт
`base`. Аргументы валидируются в конструкторе.

### `Backoff\ConstantBackoffPolicy`
Фиксированная задержка на каждый повтор.

### `Backoff\FullJitterBackoffPolicy`
Декоратор, возвращающий случайную задержку в `[0, inner.delayFor()]`; принимает
инъекцию рандомайзера для детерминируемых тестов.

### `Worker\Worker`
Реализует `WorkerInterface`. Публикует `JobProcessing` / `JobProcessed` /
`JobFailed`. Бросающий `failed()` никогда не уронит цикл.

### `Worker\WorkerOptions` *(final readonly)*
`maxJobs`, `sleepSeconds`, `stopWhenEmpty`, `memoryLimitMb`; `WorkerOptions::once()`.

### `Worker\CoroutineSleeper` / `Worker\NullSleeper`
Корутинное ожидание Swoole; и no-op для тестов.

### `FailedJob\InMemoryFailedJobStore`
Хранилище dead-letter в рамках процесса.

### `Support\SystemClock` / `Support\MutableClock` / `Support\FailedJobRecord`
Системное время; ручные часы для тестов; неизменяемая запись об ошибке
(`id`, `jobClass`, `exceptionClass`, `message`, `attempts`, `failedAt`).

## События (`Momo\Queue\Events`)
`JobProcessing`, `JobProcessed`, `JobFailed` — расширяют `Momo\Events\DomainEvent`
и несут `Envelope` (и `\Throwable` для `JobFailed`).

## Консоль
- `queue:work` — `--once`, `--max-jobs`, `--sleep`, `--memory`.
- `queue:failed` — табличный список dead-letter.
