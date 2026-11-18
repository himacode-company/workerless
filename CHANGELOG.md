# Changelog

All notable changes to Workerless are documented here.
This project follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-18

First release.

### Added

- `workerless` queue driver built on Laravel's `DatabaseQueue` and the standard `jobs` table. The connection registers itself: set `QUEUE_CONNECTION=workerless` and you are done.
- Jobs run right after the response is sent (LiteSpeed, PHP-FPM, Apache mod_php, built-in server), inside the same PHP that serves the site, with time and job limits and a single-runner lock. Visitors never wait.
- Jobs run with Laravel's own Worker: tries, backoff, `failed_jobs`, `queue:retry` and queue events behave exactly like `queue:work`.
- Jobs start from a clean state, like `queue:work`: no visitor login, request, locale or scoped services leak into them.
- Signed self-call chain keeps batches, delayed jobs and retries moving without visitors (HMAC from `APP_KEY`, short validity, one-time nonce, 404 on any bad request, URL from config only). It looks ahead `WORKERLESS_HORIZON` seconds (default 300) in hops of `max_wait`, survives a crashing job, and retries a failed self-call once.
- Monitoring mode for a VPS (`WORKERLESS_ENABLED=false`): your own `queue:work` runs the jobs, Workerless records them and shows them in the dashboard. A lightweight Horizon for the database queue.
- Tables are created automatically (`jobs`, `failed_jobs`, `workerless_runs`), no `php artisan migrate` needed. A package migration keeps `migrate` and `migrate:fresh` working.
- Built-in hosting check: real tests on the server with a clear verdict (Compatible, Works with limits, Not compatible) and the reason.
- Dashboard at `/workerless` with a Simple mode (status, key numbers, failed jobs) and an Advanced mode (throughput chart, jobs, failed jobs, history, health checks). Light and dark themes, live refresh, responsive.
- Dashboard access by user id (`WORKERLESS_USER_IDS`) or a `viewWorkerless` gate. Everyone else gets a 404.
- Job history in `workerless_runs` (never the job payload), pruned after `history.keep_days`.
- On install: `config/workerless.php` is copied into the app with every option explained (never overwriting an existing file), and `.env` / `.env.example` get the Workerless keys and a hint.
