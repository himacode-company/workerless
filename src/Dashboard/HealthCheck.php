<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Dashboard;

use HimaCode\Workerless\Runner\Runner;
use HimaCode\Workerless\Support\Installer;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Finds problems and explains how to fix each one.
 * Every check returns: level (ok | warn | fail), title, detail.
 */
class HealthCheck
{
    public function __construct(
        protected Application $app,
        protected Config $config,
        protected Metrics $metrics,
    ) {
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<int, array{level: string, title: string, detail: string}>
     */
    public function run(array $summary): array
    {
        $checks = [];

        $checks[] = $this->config->get('queue.default') === Runner::CONNECTION
            ? $this->ok('Workerless is the default queue', 'QUEUE_CONNECTION=workerless')
            : $this->warn('Workerless is not the default queue', 'Jobs go to "' . $this->config->get('queue.default') . '". Set QUEUE_CONNECTION=workerless in .env, or dispatch with ->onConnection(\'workerless\').');

        $checks[] = $this->config->get('workerless.enabled', true)
            ? $this->ok('Runner enabled', 'Jobs run right after responses.')
            : $this->warn('Runner disabled', 'WORKERLESS_ENABLED=false: jobs are stored but never run.');

        $checks[] = $this->accessCheck();

        $checks[] = $this->tablesCheck();

        $storage = $this->app->storagePath('framework');
        $checks[] = is_writable($storage)
            ? $this->ok('Storage is writable', 'State files live in storage/framework/workerless.')
            : $this->fail('Storage is not writable', 'Workerless cannot keep its state. Make storage/framework writable by PHP.');

        $checks[] = $this->serverCheck();

        $checks[] = $this->loopbackCheck();

        $stuck = (int) ($summary['stuck'] ?? 0);
        $checks[] = $stuck === 0
            ? $this->ok('No stuck jobs', 'No job is held longer than retry_after (' . $this->metrics->retryAfter() . ' s).')
            : $this->warn($stuck . ' stuck ' . ($stuck === 1 ? 'job' : 'jobs'), 'Held longer than retry_after. They will be picked up again automatically. If this repeats, the jobs take too long for this server.');

        $checks[] = $this->backlogCheck($summary);

        $maxExecution = (int) ini_get('max_execution_time');
        $retryAfter = $this->metrics->retryAfter();
        $canExtend = function_exists('set_time_limit');
        $checks[] = ($canExtend || $maxExecution === 0 || $maxExecution >= $retryAfter)
            ? $this->ok('Time limits fit', 'Jobs can run up to retry_after (' . $retryAfter . ' s).')
            : $this->warn('Time limit is short', 'max_execution_time is ' . $maxExecution . ' s and set_time_limit() is disabled. Long jobs may be cut off.');

        return $checks;
    }

    /** @param  array<int, array{level: string}>  $checks */
    public static function worst(array $checks): string
    {
        $levels = array_column($checks, 'level');

        return in_array('fail', $levels, true) ? 'fail' : (in_array('warn', $levels, true) ? 'warn' : 'ok');
    }

    public static function serverMethod(): string
    {
        return match (true) {
            function_exists('litespeed_finish_request') => 'LiteSpeed',
            function_exists('fastcgi_finish_request') => 'PHP-FPM',
            PHP_SAPI === 'apache2handler' => 'Apache mod_php',
            PHP_SAPI === 'cli-server' => 'Built-in server',
            default => strtoupper(PHP_SAPI),
        };
    }

    /** @return array{level: string, title: string, detail: string} */
    protected function accessCheck(): array
    {
        $ids = trim((string) implode(',', (array) $this->config->get('workerless.dashboard.user_ids', '')), ', ');

        if ($ids !== '') {
            return $this->ok('Dashboard access set', 'Open for user ids: ' . $ids . '. Everyone else gets a 404.');
        }

        if (Gate::has('viewWorkerless')) {
            return $this->ok('Dashboard access set', 'Controlled by your viewWorkerless gate. Everyone else gets a 404.');
        }

        return $this->warn('Nobody can open this page in production', 'Add your user id to .env before going live, for example WORKERLESS_USER_IDS=1');
    }

    /** @return array{level: string, title: string, detail: string} */
    protected function tablesCheck(): array
    {
        try {
            $schema = $this->metrics->jobsConnection()->getSchemaBuilder();
            $missing = [];

            foreach ([$this->metrics->jobsTableName(), Installer::RUNS_TABLE] as $table) {
                if (! $schema->hasTable($table)) {
                    $missing[] = $table;
                }
            }

            if ($this->metrics->failedStoreIsDatabase()) {
                $failedTable = (string) $this->config->get('queue.failed.table', 'failed_jobs');
                $failedSchema = $this->app->make('db')->connection($this->config->get('queue.failed.database'))->getSchemaBuilder();

                if (! $failedSchema->hasTable($failedTable)) {
                    $missing[] = $failedTable;
                }
            }
        } catch (Throwable) {
            return $this->fail('Database unreachable', 'Workerless could not reach the queue database. Check the DB settings in .env.');
        }

        return $missing === []
            ? $this->ok('Tables ready', 'jobs, failed_jobs and workerless_runs exist.')
            : $this->fail('Missing tables: ' . implode(', ', $missing), 'They are created automatically on the next job. If not, the database user may lack CREATE permission.');
    }

    /** @return array{level: string, title: string, detail: string} */
    protected function serverCheck(): array
    {
        $method = self::serverMethod();

        return match ($method) {
            'LiteSpeed', 'PHP-FPM' => $this->ok('Server: ' . $method, 'The response is finished first, then jobs run. Visitors never wait.'),
            'Apache mod_php', 'Built-in server' => $this->ok('Server: ' . $method, 'The connection is closed manually, then jobs run. Streamed and file downloads are skipped.'),
            default => $this->warn('Server: ' . $method, 'Unknown server type. Jobs may delay responses here.'),
        };
    }

    /** @return array{level: string, title: string, detail: string} */
    protected function loopbackCheck(): array
    {
        if (! $this->config->get('workerless.loopback.enabled', true)) {
            return $this->warn('Self-calls disabled', 'Delayed jobs and retries wait for visitors.');
        }

        if ((string) $this->config->get('app.key', '') === '') {
            return $this->fail('APP_KEY is empty', 'Self-calls are signed with APP_KEY and stay off without it. Run php artisan key:generate.');
        }

        $url = (string) ($this->config->get('workerless.loopback.url') ?: $this->config->get('app.url', ''));
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return $this->fail('APP_URL is not set', 'Self-calls need the site address. Set APP_URL in .env.');
        }

        if (! function_exists('curl_init')) {
            return $this->warn('cURL is missing', 'Self-calls need the PHP curl extension.');
        }

        if ($this->app->environment('production') && in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $this->warn('APP_URL points to ' . $host, 'In production APP_URL must be your real domain.');
        }

        return $this->ok('Self-calls ready', 'Signed calls to ' . $host . ' keep delayed jobs and retries moving.');
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{level: string, title: string, detail: string}
     */
    protected function backlogCheck(array $summary): array
    {
        $oldest = $summary['oldest_pending'] ?? null;

        if ($oldest === null) {
            return $this->ok('No backlog', 'Every ready job has been picked up.');
        }

        $waiting = time() - (int) $oldest;

        if ($waiting < 600) {
            return $this->ok('Backlog moving', 'The oldest ready job has waited ' . Format::ago((int) $oldest, '') . '.');
        }

        return $this->warn('Jobs are waiting', 'The oldest ready job has waited ' . intdiv($waiting, 60) . ' minutes. Workerless runs on traffic and self-calls: check the self-call status above.');
    }

    /** @return array{level: string, title: string, detail: string} */
    protected function ok(string $title, string $detail): array
    {
        return ['level' => 'ok', 'title' => $title, 'detail' => $detail];
    }

    /** @return array{level: string, title: string, detail: string} */
    protected function warn(string $title, string $detail): array
    {
        return ['level' => 'warn', 'title' => $title, 'detail' => $detail];
    }

    /** @return array{level: string, title: string, detail: string} */
    protected function fail(string $title, string $detail): array
    {
        return ['level' => 'fail', 'title' => $title, 'detail' => $detail];
    }
}
