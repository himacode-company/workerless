<p align="center">
    <img src="art/logo.svg" width="112" height="112" alt="Workerless logo">
</p>

<h1 align="center">Workerless</h1>

<p align="center"><strong>Laravel queues without cron, SSH or <code>queue:work</code>.</strong><br>Built for shared hosting.</p>

<p align="center">
    <a href="https://packagist.org/packages/himacode-company/workerless"><img src="https://img.shields.io/packagist/v/himacode-company/workerless.svg?style=flat-square&color=FF5A36" alt="Latest version"></a>
    <a href="https://packagist.org/packages/himacode-company/workerless"><img src="https://img.shields.io/packagist/php-v/himacode-company/workerless.svg?style=flat-square" alt="PHP version"></a>
    <a href="LICENSE"><img src="https://img.shields.io/packagist/l/himacode-company/workerless.svg?style=flat-square" alt="License"></a>
</p>

---

On shared hosting you usually cannot keep `php artisan queue:work` running, and often you have no SSH and no cron either. So emails, notifications and webhooks end up running while the visitor waits.

**Workerless is a queue driver that needs none of that.** Your jobs are stored in the normal `jobs` table and run **right after the response is sent**, so visitors never wait.

## Features

- **Zero setup.** `composer require` and one line in `.env`. No cron, no SSH, no supervisor, no hPanel or cPanel step.
- **Real Laravel queues.** Built on Laravel's own `DatabaseQueue` and Worker: `$tries`, `$backoff`, `delay()`, `failed_jobs`, `queue:retry` and queue events all keep working.
- **Visitors never wait.** The response is finished first, then jobs run in the same PHP process.
- **Delays and retries without visitors.** A signed self-call keeps them moving.
- **Hosting check built in.** One click tells you if your hosting is *Compatible*, *Works with limits* or *Not compatible*, and why.
- **Dashboard.** Status, failed jobs with retry, history, health checks. Simple and Advanced modes, light and dark.
- **No tables to create.** Workerless creates them for you.
- **Secure by default.** No shell commands, signed one-time self-calls, a dashboard that returns 404 to anyone not allowed.
- **No lock-in.** Outgrow shared hosting? Run `php artisan queue:work` on the same `jobs` table. Nothing else changes.

## Installation

**1. Install** (in `require`, not `require-dev`: it runs in production)

```bash
composer require himacode-company/workerless
```

**2. Use it** in `.env`

```env
QUEUE_CONNECTION=workerless
```

**3. Check your hosting.** Upload your app, open `/workerless` and press **Check this hosting**. In about 5 seconds you know if Workerless fully works there.

That's it. Dispatch jobs as usual:

```php
SendWelcomeEmail::dispatch($user);
SendReminder::dispatch($user)->delay(now()->addMinutes(10));
```

## How it works

1. A job is dispatched and stored in the `jobs` table.
2. The current response is sent to the visitor (`litespeed_finish_request`, `fastcgi_finish_request`, or a closed connection on Apache mod_php).
3. Right after, in the same PHP process, Workerless runs the due jobs with Laravel's Worker, within time and job limits, one run at a time.
4. If a job is due soon (a delay or a retry), Workerless sends a signed request to itself, which waits and runs it, even if nobody visits the site.

## Dashboard

Open `/workerless`.

- **Simple mode:** is everything working, how many jobs are waiting, done and failed, and the failed jobs with Retry and Delete.
- **Advanced mode:** throughput chart, all jobs, failed jobs, history, health checks and server details.

It opens without login only in the `local` environment and only from the same machine (`127.0.0.1`). In production, list the ids (from your `users` table) of the people allowed to open it. The empty key is added to your `.env` when you install the package:

```env
WORKERLESS_USER_IDS=1,7
```

They must be logged in to your app. Everyone else gets a **404**, so nobody can tell the dashboard exists.

Need a custom rule (roles, permissions)? Define a `viewWorkerless` gate, for example in `AppServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewWorkerless', fn ($user) => $user->is_admin);
```

## Configuration

Everything works out of the box. These `.env` keys are optional:

| Key | Default | What it does |
| --- | --- | --- |
| `WORKERLESS_USER_IDS` | empty | User ids allowed to open the dashboard |
| `WORKERLESS_ENABLED` | `true` | Turn job running off (jobs stay stored) |
| `WORKERLESS_MAX_JOBS` | `10` | Jobs per run |
| `WORKERLESS_MAX_SECONDS` | `30` | Stop taking new jobs after this many seconds |
| `WORKERLESS_COOLDOWN` | `5` | Seconds between two runs |
| `WORKERLESS_QUEUES` | connection queue | Queues to run, by priority, e.g. `high,default` |
| `WORKERLESS_LOOPBACK` | `true` | Signed self-calls for delays and retries |
| `WORKERLESS_URL` | `APP_URL` | Address used for self-calls |
| `WORKERLESS_MAX_WAIT` | `30` | Longest a self-call waits for the next job (seconds) |
| `WORKERLESS_VERIFY_SSL` | `true` | Keep `true` in production |
| `WORKERLESS_DASHBOARD` | `true` | Turn the dashboard off |
| `WORKERLESS_PATH` | `workerless` | Dashboard URL |
| `WORKERLESS_KEEP_DAYS` | `7` | Days of job history to keep |

To edit the config file itself:

```bash
php artisan vendor:publish --tag=workerless-config
```

## When to use it, and when not

**Great for** small and medium apps on shared hosting: emails, notifications, webhooks, image resizing, short API calls.

**Not for:**

- **High volume.** Up to 10 jobs per run, one run at a time. Thousands of jobs per hour need a real worker.
- **Long jobs.** A job must finish within `retry_after` (90 s by default). Video processing or huge exports need a real worker.
- **Exact timing with no traffic.** A job delayed by more than 30 s runs at the first visit after its time.
- **Several servers** behind a load balancer, or Laravel Octane.

Each run uses one PHP process of your hosting for a few seconds, so very busy sites may hit their hosting's process limit.

## Tables

Workerless creates `jobs`, `failed_jobs` and `workerless_runs` by itself, and also ships a migration, so `php artisan migrate` and `migrate:fresh` keep working. Do not run `php artisan queue:table`: the `jobs` table already exists.

## FAQ

**Can I still use `queue:work`?** Yes. Workerless uses the standard `jobs` table, so a real worker can process the same jobs at any time.

**Does it work behind Cloudflare?** Yes. Self-calls go to `127.0.0.1` first, so they usually never leave the server. If they are blocked, the hosting check tells you.

**What if my hosting is not compatible?** The hosting check says so, and why, before you rely on it. Your jobs stay safely in the `jobs` table.

**Is it safe?** See [SECURITY.md](SECURITY.md). No shell commands are ever run, self-calls are signed and can be used only once, and the dashboard is invisible to anyone not allowed.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12 or 13
- A database supported by Laravel's database queue (MySQL, MariaDB, PostgreSQL, SQLite, SQL Server)

## Security

Please report vulnerabilities privately, see [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).

---

<div dir="rtl">

## بالعربية

**Queue في Laravel بلا Cron ولا SSH ولا `queue:work`**، مصمَّمة للاستضافة المشتركة.

على الاستضافة المشتركة لا تستطيع غالبًا تشغيل `queue:work` بشكل دائم، وكثيرًا لا يوجد SSH ولا Cron. Workerless تحفظ الـ Jobs في جدول `jobs` المعتاد، وتنفّذها مباشرة بعد إرسال الصفحة للزائر، فلا ينتظر أحد.

### التثبيت

1. `composer require himacode-company/workerless`
2. في ملف `.env`: `QUEUE_CONNECTION=workerless`
3. ارفع تطبيقك، وافتح `/workerless`، واضغط **Check this hosting**. خلال 5 ثوانٍ تعرف هل تعمل Workerless على استضافتك بالكامل.

### لوحة التحكم

في الـ production، اكتب أرقام المستخدمين (من جدول `users`) المسموح لهم بفتح اللوحة:

```env
WORKERLESS_USER_IDS=1,7
```

أي شخص آخر يرى صفحة 404.

### مناسبة لـ

المواقع الصغيرة والمتوسطة: الإيميلات، الإشعارات، الـ Webhooks، المهام القصيرة. وليست للكميات الضخمة أو الأعمال الطويلة جدًا. وإذا كبر تطبيقك، شغّل `queue:work` على الجدول نفسه دون أي تغيير.

</div>

---

<p align="center">Made by <a href="https://github.com/himacode-company">HimaCode</a></p>
