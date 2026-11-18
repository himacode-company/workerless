# Changelog

All notable changes to Workerless are documented here.
This project follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-18

First release.

### Added

- `workerless` queue driver built on Laravel's `DatabaseQueue` and the standard `jobs` table. The connection registers itself: set `QUEUE_CONNECTION=workerless` and you are done.
- Jobs run right after the response is sent (LiteSpeed, PHP-FPM, Apache mod_php, built-in server), with time and job limits and a single-runner lock. Visitors never wait.
- Jobs run with Laravel's own Worker: tries, backoff, `failed_jobs`, `queue:retry` and queue events behave exactly like `queue:work`.
- Jobs start from a clean state, like `queue:work`: no visitor login, request, locale or scoped services leak into them.
- Signed self-call chain keeps delayed jobs and retries moving without visitors (HMAC from `APP_KEY`, short validity, one-time nonce, 404 on any bad request, URL from config only).
- Tables are created automatically (`jobs`, `failed_jobs`, `workerless_runs`), no `php artisan migrate` needed. A package migration keeps `migrate` and `migrate:fresh` working.
- Built-in hosting check: real tests on the server with a clear verdict (Compatible, Works with limits, Not compatible) and the reason.
- Dashboard at `/workerless` with a Simple mode (status, key numbers, failed jobs) and an Advanced mode (throughput chart, jobs, failed jobs, history, health checks). Light and dark themes, live refresh, responsive.
- Dashboard access by user id (`WORKERLESS_USER_IDS`) or a `viewWorkerless` gate. Everyone else gets a 404.
- Job history in `workerless_runs` (never the job payload), pruned after `history.keep_days`.
- `.env` and `.env.example` get the Workerless keys and a hint on install.
