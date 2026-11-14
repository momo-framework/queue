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
  <b>Առաջադրանքների հերթ Momo-ի համար RabbitMQ-ի վրա․ մշակիչներ մոդուլներում, ստորագրված առաջադրանքներ, զուգահեռ worker-ներ coroutine-ներով։</b>
</div>

<div align="center">
  <a href="README.md">🇬🇧 English</a> · <a href="README.ru.md">🇷🇺 Русский</a> · 🇦🇲 Հայերեն
</div>

---

## Հնարավորություններ

- Առաջադրանքը արժեքների պարզ օբյեկտ է, իր մշակիչը `JobHandler` է `__invoke(SendInvoice $job)`-ով մոդուլի `Presentation/Queue/`-ում, գտնվում է առաջադրանքի տիպով
- `#[Job(queue: 'mail', attempts: 5, backoff: [10, 60, 300])]`-ը տալիս է հերթը, փորձերի քանակը և կրկնության ուշացումները; լռելյայն ուշացումը էքսպոնենցիալ է՝ լրիվ ջիթերով
- Միայն RabbitMQ․ կայուն հերթեր, պահպանվող հաղորդագրություններ, ուշացումը TTL-ով հերթ է, որը հաղորդագրությունը վերադարձնում է իր հերթ, ձախողված առաջադրանքները սպասում են `momo.failed`-ում
- Ամեն առաջադրանք ստորագրված է `APP_KEY`-ով․ բրոքերում փոխված կամ այլ հավելվածի ստորագրած մարմինը չի բացվում և անմիջապես գնում է ձախողվածների մեջ
- Մահացած worker-ի առաջադրանքը վերադառնում է հերթ, երբ բրոքերը տեսնում է, որ կապը կորել է
- `queue:work --concurrency=20`․ քսան առաջադրանք միաժամանակ մեկ պրոցեսում, SIGTERM-ը թույլ է տալիս ավարտել դրանք
- Եթե բրոքերը անհասանելի է `--max-failures` հարցում անընդմեջ (լռելյայն 12, մոտ մեկ րոպե), `queue:work`-ը դուրս է գալիս 1 կոդով, և docker-ը կամ supervisor-ը նոր պրոցես է գործարկում՝ մեռած կապի վրա worker պահելու փոխարեն
- Ձախողված առաջադրանքները պահվում են․ `queue:failed`, `queue:retry <id>|all`; `queue:list`-ը ցույց է տալիս ամեն առաջադրանք՝ մշակիչով, հերթով և փորձերով
- `memory` տրանսպորտ թեստերի համար․ `queue.transport = memory`-ն առաջադրանքները պահում է պրոցեսում
- Bus-ի հրամանը `#[Queued(queue: 'reports', attempts: 3)]`-ով գնում է հերթ իրավունքների ստուգումից հետո, ուղարկողը ստանում է `null`; worker-ը այն տալիս է նույն bus-ին նույն օգտատիրոջ և թիմի անունից, ուստի իրավունքները, տրանզակցիան և commit-ից հետո իրադարձությունները այնտեղ նորից են աշխատում։ Հավելվածի ամեն `Momo\Bus\Contracts\Context` իր վիճակը տանում է հրամանի հետ

## Ինչու

Միաժամանակ մեկ առաջադրանք կատարող worker-ը պարապ է մնում, մինչ առաջադրանքը սպասում է HTTP-ին կամ SQL-ին։ Coroutine-ներով նույն պրոցեսը մեկ CPU-ի վրա պահում է քսան առաջադրանք։ Մշակիչը կոնտրոլերների կողքին է, քանի որ հերթը մոդուլ մտնելու ևս մեկ ճանապարհ է, իսկ առաջադրանքը մնում է արժեք-օբյեկտ, և այն դնող կոդը կախված չէ այն կատարող կոդից։

## Արագ մեկնարկ

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

## Կարգավորում

- `QUEUE_NAME`, լռելյայն `default`․ առանց `#[Job(queue:)]` առաջադրանքի հերթը
- `QUEUE_RETRY_AFTER`, լռելյայն 90․ քանի վայրկյան կարող է աշխատել վերցված առաջադրանքը
- `QUEUE_FAILED`, լռելյայն `momo.failed`․ ձախողված առաջադրանքների հերթը
- `AMQP_HOST`, `AMQP_PORT`, `AMQP_USER`, `AMQP_PASSWORD`, `AMQP_VHOST`․ որտեղ է RabbitMQ-ն, գաղտնաբառը գաղտնիք է
- `AMQP_HEARTBEAT` (30), `AMQP_TIMEOUT` (5.0), `AMQP_POOL` (32)․ կապի heartbeat-ը և ժամանակային սահմանները, կապեր մեկ worker-ի համար

## Մշակում

```bash
composer install
composer check
```

RabbitMQ-ի թեստերը աշխատում են, երբ տրված է `AMQP_HOST`․ `docker run -d -p 5672:5672 rabbitmq:4-alpine`, հետո `AMQP_HOST=127.0.0.1 composer test`։

## Կարգավիճակ

0.6. Հանրային API․ `Contracts\Queue`, `Contracts\JobHandler`, `Contracts\Transport`, `Job`, `Envelope`, `FailedJob`, `Worker`, `WorkerOptions`։

## Լիցենզիա

[AGPL-3.0-or-later](LICENSE).

---

<div align="center">
  <sub>© 2024 - 2026 Anidzen Labs. Innovation through simplicity.</sub><br/>
  <sub>Built by <a href="https://vahe-sargsyan.com/">w33bvGL</a></sub>
</div>
