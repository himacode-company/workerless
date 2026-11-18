<?php

/*
|==========================================================================
| Workerless
|==========================================================================
|
| Laravel queues without cron, SSH or `queue:work`.
|
| Your jobs are stored in the normal `jobs` table and run right after a
| response is sent, so visitors never wait. Delayed jobs and retries keep
| moving through signed requests the app sends to itself.
|
| To use it, set in your .env:
|
|     QUEUE_CONNECTION=workerless
|
| Every value below already has the safe choice. You can change any of them
| here, or (better) through the .env key written next to it, so each server
| can have its own value without touching this file.
|
| After changing .env on a server that ran `php artisan config:cache`,
| run `php artisan config:clear` (or `config:cache` again).
|
| Docs: https://github.com/himacode-company/workerless#configuration
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Mode
    |----------------------------------------------------------------------
    |
    | true  (default) Workerless runs your jobs: shared hosting, local dev.
    |
    | false MONITORING MODE, for a VPS. Your own `php artisan queue:work`
    |       (Supervisor, systemd...) runs the jobs. Workerless only records
    |       them and shows them in the dashboard: a lightweight Horizon for
    |       the database queue. Jobs are never lost: they stay in `jobs`.
    |
    */

    'enabled' => env('WORKERLESS_ENABLED', true),

    /*
    |----------------------------------------------------------------------
    | Runner: how much work one run may do
    |----------------------------------------------------------------------
    |
    | A "run" happens right after a response, in the same PHP process.
    | These limits keep each run short and gentle on shared hosting.
    |
    | max_seconds  Stop taking new jobs after this many seconds.
    |              Raise it for long batches, keep it under your hosting's
    |              max_execution_time.                       Default: 30
    |
    | max_jobs     Jobs per run. Raise it to empty big batches faster
    |              (e.g. 20 for hundreds of emails).          Default: 10
    |
    | cooldown     Seconds between two runs. Lower = faster batches but
    |              more load; 0 runs back to back.            Default: 5
    |
    | queues       Queues to run, in priority order, comma separated.
    |              Example: "high,default,low". Null = the queue of the
    |              workerless connection, usually "default".  Default: null
    |
    */

    'runner' => [
        'max_seconds' => (int) env('WORKERLESS_MAX_SECONDS', 30),
        'max_jobs' => (int) env('WORKERLESS_MAX_JOBS', 10),
        'cooldown' => (int) env('WORKERLESS_COOLDOWN', 5),
        'queues' => env('WORKERLESS_QUEUES'),
    ],

    /*
    |----------------------------------------------------------------------
    | Self-calls: delays and retries without visitors
    |----------------------------------------------------------------------
    |
    | When work is due soon, the app sends a signed request to itself that
    | waits and runs it, then passes the baton to the next one. A chain.
    | Signed with a key derived from APP_KEY, valid once, 404 otherwise.
    |
    | enabled        Turn self-calls off: delayed jobs and retries then
    |                wait for the next visitor.               Default: true
    |
    | url            Address the app calls. Null = APP_URL. Set it only
    |                if APP_URL is not the real public address.
    |                It is never taken from the incoming request.
    |                                                         Default: null
    |
    | signature_ttl  Seconds a signed self-call stays valid.  Default: 60
    |
    | max_wait       Longest one self-call waits for the next job, in
    |                seconds (max 300). While it waits it holds one PHP
    |                process, so keep it short on shared hosting.
    |                                                         Default: 30
    |
    | horizon        How far ahead the chain stays alive, in seconds
    |                (max 900). Work due within this time (e.g. a retry
    |                with a 60 s backoff) is reached in hops of max_wait.
    |                Later work waits for the next visitor.   Default: 300
    |
    | verify_ssl     Check the HTTPS certificate on self-calls. Keep true
    |                in production; false only for local self-signed HTTPS.
    |                                                         Default: true
    |
    */

    'loopback' => [
        'enabled' => env('WORKERLESS_LOOPBACK', true),
        'url' => env('WORKERLESS_URL'),
        'signature_ttl' => 60,
        'max_wait' => (int) env('WORKERLESS_MAX_WAIT', 30),
        'horizon' => (int) env('WORKERLESS_HORIZON', 300),
        'verify_ssl' => env('WORKERLESS_VERIFY_SSL', true),
    ],

    /*
    |----------------------------------------------------------------------
    | Dashboard
    |----------------------------------------------------------------------
    |
    | Open /workerless. Who can open it:
    |
    |   - logged-in users whose id is in WORKERLESS_USER_IDS, e.g. "1,7"
    |   - users allowed by a `viewWorkerless` gate, if your app defines one:
    |         Gate::define('viewWorkerless', fn ($user) => $user->is_admin);
    |   - anyone in the `local` environment, from the same machine only.
    |
    | Everyone else gets a 404, as if the page did not exist.
    |
    | enabled     Turn the dashboard off.                     Default: true
    | path        URL of the dashboard.                       Default: workerless
    | middleware  Middleware for the dashboard. Keep "web" (sessions,
    |             login, CSRF). Add your own, e.g. ['web', 'verified'].
    | user_ids    Comma-separated user ids allowed in production.
    |
    */

    'dashboard' => [
        'enabled' => env('WORKERLESS_DASHBOARD', true),
        'path' => env('WORKERLESS_PATH', 'workerless'),
        'middleware' => ['web'],
        'user_ids' => env('WORKERLESS_USER_IDS', ''),
    ],

    /*
    |----------------------------------------------------------------------
    | History
    |----------------------------------------------------------------------
    |
    | Every finished job is written to the `workerless_runs` table for the
    | dashboard: class name, queue, status, attempts, duration and a short
    | error. Never the job payload.
    |
    | keep_days   Older history is deleted automatically.     Default: 7
    |
    */

    'history' => [
        'keep_days' => (int) env('WORKERLESS_KEEP_DAYS', 7),
    ],

];
