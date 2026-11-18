<?php

/*
|--------------------------------------------------------------------------
| Workerless
|--------------------------------------------------------------------------
|
| Runs your queued jobs without cron, SSH or queue:work.
| Every default here is the safe choice. Change them only if you know why.
|
| To use it, set in your .env:  QUEUE_CONNECTION=workerless
|
*/

return [

    /*
    | Master switch. When false, Workerless never runs jobs after a response
    | and never calls itself. Jobs stay safely stored in the `jobs` table.
    */
    'enabled' => env('WORKERLESS_ENABLED', true),

    /*
    | Limits for one run after a response, so a run can never overload the server.
    |  - max_seconds: stop taking new jobs after this many seconds.
    |  - max_jobs:    stop after this many jobs.
    |  - cooldown:    minimum seconds between two runs.
    |  - queues:      queues to run, in priority order, e.g. "high,default".
    |                 Null = the queue of the workerless connection ("default").
    */
    'runner' => [
        'max_seconds' => (int) env('WORKERLESS_MAX_SECONDS', 30),
        'max_jobs' => (int) env('WORKERLESS_MAX_JOBS', 10),
        'cooldown' => (int) env('WORKERLESS_COOLDOWN', 5),
        'queues' => env('WORKERLESS_QUEUES'),
    ],

    /*
    | Self-call: the app sends a signed request to itself to keep delayed jobs
    | and retries moving when there are no visitors.
    |  - url: base URL used for the self-call. Null = config('app.url').
    |         It is NEVER taken from the incoming request (Host header).
    |  - signature_ttl: seconds a signed self-call stays valid (each one works once).
    |  - max_wait: a self-call waits at most this many seconds for the next job
    |              (max 300). Jobs due later wait for the next visitor. While it
    |              waits it holds one PHP process, so keep it short on shared hosting.
    |  - verify_ssl: keep true in production. False only for local self-signed HTTPS.
    */
    'loopback' => [
        'enabled' => env('WORKERLESS_LOOPBACK', true),
        'url' => env('WORKERLESS_URL'),
        'signature_ttl' => 60,
        'max_wait' => (int) env('WORKERLESS_MAX_WAIT', 30),
        'verify_ssl' => env('WORKERLESS_VERIFY_SSL', true),
    ],

    /*
    | Admin dashboard. Outside the `local` environment it opens only for:
    |  - logged-in users whose id is in WORKERLESS_USER_IDS, e.g. WORKERLESS_USER_IDS=1,7
    |  - or users allowed by a `viewWorkerless` gate, if your app defines one.
    | Everyone else gets a 404, as if the page did not exist.
    */
    'dashboard' => [
        'enabled' => env('WORKERLESS_DASHBOARD', true),
        'path' => env('WORKERLESS_PATH', 'workerless'),
        'middleware' => ['web'],
        'user_ids' => env('WORKERLESS_USER_IDS', ''),
    ],

    /*
    | History of finished jobs in the `workerless_runs` table.
    | Job payloads are never stored, only the job class, status and timings.
    */
    'history' => [
        'keep_days' => (int) env('WORKERLESS_KEEP_DAYS', 7),
    ],

];
