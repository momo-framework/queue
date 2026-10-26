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
  <b>Առաջադրանքների հերթ Momo-ի համար՝ Redis և տվյալների բազայի տրանսպորտներով։</b><br/>
  Մշակիչները մոդուլի Presentation/Queue-ում են, մեկ worker-ը միանգամից շատ առաջադրանքներ է կատարում coroutine-ներում։
</div>

<div align="center">
  <a href="README.md">🇬🇧 English</a> · <a href="README.ru.md">🇷🇺 Русский</a> · 🇦🇲 Հայերեն
</div>

---

## Հնարավորություններ

- Առաջադրանքը արժեքներով օբյեկտ է, իսկ մշակիչը՝ `JobHandler` `__invoke(SendInvoice $job)`-ով մոդուլի `Presentation/Queue/`-ում, որը գտնվում է առաջադրանքի տիպով
- `#[Job(queue: 'mail', attempts: 5, backoff: [10, 60, 300])]`-ը սահմանում է հերթը, փորձերի քանակը և դադարները. լռելյայն էքսպոնենցիալ է full jitter-ով
- `redis` տրանսպորտ. պատրաստ առաջադրանքների ցուցակ, հետաձգված և զբաղված sorted set-եր, յուրաքանչյուր քայլ մեկ Lua script, ընկած worker-ի առաջադրանքը վերադառնում է `retry_after` վայրկյանից
- `database` տրանսպորտ. տրանզակցիայի ներսում դրված առաջադրանքը commit է լինում տվյալների հետ. worker-ները վերցնում են `FOR UPDATE SKIP LOCKED`-ով
- `sync`-ը մշակիչը կատարում է անմիջապես, `memory`-ն առաջադրանքները պահում է պրոցեսում թեստերի համար
- `queue:work --concurrency=20`. քսան առաջադրանք միանգամից մեկ պրոցեսում, SIGTERM-ով ընթացիկները ավարտվում են
- Ձախողված առաջադրանքները պահվում են. `queue:failed`, `queue:retry <id>|all`
- `queue:list`-ը ցույց է տալիս բոլոր առաջադրանքները մշակիչով, հերթով և փորձերի քանակով
- `make:job Billing SendInvoice`-ը ստեղծում է առաջադրանքը և նրա մշակիչը

## Ինչու

Միաժամանակ մեկ առաջադրանք կատարող worker-ը պարապ է մնում, մինչ առաջադրանքը սպասում է HTTP-ին կամ SQL-ին։ Coroutine-ներով նույն պրոցեսը մեկ CPU-ի վրա պահում է քսան առաջադրանք։ Մշակիչը կոնտրոլերների կողքին է, քանի որ հերթը մոդուլ մտնելու ևս մեկ ճանապարհ է, իսկ առաջադրանքը մնում է արժեք-օբյեկտ, և այն դնող կոդը կախված չէ այն կատարող կոդից։

## Արագ մեկնարկ

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

## Կարգավորում

- `QUEUE_CONNECTION`, լռելյայն `sync`. `sync`, `memory`, `redis` կամ `database`
- `QUEUE_REDIS_CONNECTION`, լռելյայն `default`. կապ `config/redis.php`-ից
- `QUEUE_DB_CONNECTION`. կապ `config/database.php`-ից, դատարկի դեպքում հիմնականը
- `config/queue.php`. `queue` (լռելյայն հերթ), `retry_after` (քանի վայրկյան է վերցված առաջադրանքը թաքնված)

`database` տրանսպորտը բերում է `jobs` և `failed_jobs` աղյուսակները, դրանք ստեղծում է `momo migrate`-ը։

## Մշակում

```bash
composer install --ignore-platform-req=ext-redis
composer check
```

Redis-ի թեստերը գործարկվում են, երբ տրված է `REDIS_HOST`, բազայի տրանսպորտը ստուգվում է SQLite-ով։

## Կարգավիճակ

0.2։ Հանրային API՝ `Contracts\Queue`, `Contracts\JobHandler`, `Contracts\Transport`, `Job`, `Envelope`, `FailedJob`, `Worker`, `WorkerOptions`։

## Լիցենզիա

[AGPL-3.0-or-later](LICENSE).

---

<div align="center">
  <sub>© 2024 - 2026 Anidzen Labs. Innovation through simplicity.</sub><br/>
  <sub>Built by <a href="https://vahe-sargsyan.com/">w33bvGL</a></sub>
</div>
