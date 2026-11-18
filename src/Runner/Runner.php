<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Runner;

use HimaCode\Workerless\History\Recorder;
use HimaCode\Workerless\Loopback\Trigger;
use HimaCode\Workerless\Support\Installer;
use HimaCode\Workerless\Support\State;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Throwable;

/**
 * Runs due jobs after the response was sent, using Laravel's own Worker,
 * so tries, backoff, failed_jobs and queue events behave exactly like queue:work.
 */
class Runner
{
    public const CONNECTION = 'workerless';

    /** Look at the database at least this often, even if no job was noted. */
    protected const SAFETY_CHECK_SECONDS = 60;

    protected bool $processing = false;

    public function __construct(
        protected Container $app,
        protected Config $config,
        protected State $state,
        protected Installer $installer,
        protected Recorder $recorder,
    ) {
    }

    /** Cheap check (file reads only): is it worth running now? */
    public function shouldRun(): bool
    {
        if (! $this->config->get('workerless.enabled', true)) {
            return false;
        }

        $now = time();

        if (! $this->state->isDue($now, self::SAFETY_CHECK_SECONDS)) {
            return false;
        }

        $lastRun = $this->state->read('last-run');
        $cooldown = max(0, (int) $this->config->get('workerless.runner.cooldown', 5));

        return $lastRun === null || $now - $lastRun >= $cooldown;
    }

    /**
     * Runs due jobs. Returns true when more work is ready soon enough
     * (within loopback.max_wait) to keep a self-call chain going.
     */
    public function run(): bool
    {
        $lock = $this->state->lock();

        if ($lock === null) {
            return false; // another request is already running jobs
        }

        $bufferLevel = ob_get_level();
        ob_start(); // nothing a job prints may reach the client
        $chain = false;
        $finished = false;

        // If PHP dies in the middle of this run (fatal error, out of memory,
        // time limit), "finally" below never runs. This still does: free the
        // lock and pass the baton, so the remaining jobs never wait for a visitor.
        register_shutdown_function(function () use (&$finished, $lock): void {
            if ($finished) {
                return;
            }

            $finished = true;
            $this->state->unlock($lock);

            try {
                $this->app->make(Trigger::class)->fire();
            } catch (Throwable) {
                // Nothing more can be done in a dying process.
            }
        });

        try {
            $this->state->write('last-run', time());

            if (function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }

            $this->installer->ensure();
            $this->freshContext();
            $this->work();
            $this->state->write('last-check', time());
            $this->refreshNextDue();
            $this->recorder->prune();

            $chain = $this->secondsUntilReady() <= $this->horizon();
        } catch (Throwable $e) {
            $this->recoverMissingTables();
            $this->report($e);
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            if (! $finished) {
                $finished = true;
                $this->state->unlock($lock);
            }
        }

        return $chain;
    }

    /** Seconds until the next job is ready AND the cooldown has passed. */
    public function secondsUntilReady(): int
    {
        $now = time();
        $next = $this->state->read('next-due') ?? $now;
        $lastRun = $this->state->read('last-run');
        $cooldown = max(0, (int) $this->config->get('workerless.runner.cooldown', 5));

        $readyAt = max($next, $lastRun === null ? $now : $lastRun + $cooldown);

        return max(0, $readyAt - $now);
    }

    /** Longest a self-call may wait for the next job (-1 = self-calls disabled). */
    public function maxWait(): int
    {
        if (! $this->config->get('workerless.loopback.enabled', true)) {
            return -1;
        }

        return max(0, min(300, (int) $this->config->get('workerless.loopback.max_wait', 30)));
    }

    /**
     * How far ahead the self-call chain keeps itself alive (-1 = disabled).
     * Work due within this many seconds is reached in hops of max_wait,
     * e.g. a retry with a 60 s backoff runs on time even with no visitors.
     */
    public function horizon(): int
    {
        $maxWait = $this->maxWait();

        if ($maxWait < 0) {
            return -1;
        }

        // Hopping needs a real wait per hop, or it would spin.
        if ($maxWait < 5) {
            return $maxWait;
        }

        return max($maxWait, min(900, (int) $this->config->get('workerless.loopback.horizon', 300)));
    }

    /**
     * Jobs run inside the visitor's PHP process. Start them from a clean state,
     * like queue:work: a plain request built from APP_URL (no visitor URL, IP,
     * headers or session), then a scope reset (see resetScope).
     */
    protected function freshContext(): void
    {
        try {
            $url = (string) $this->config->get('app.url', '');

            // instance() fires the "request" rebinding: the URL generator follows.
            $this->app->instance('request', Request::create($url !== '' ? $url : 'http://localhost'));
            Facade::clearResolvedInstance('request');
        } catch (Throwable) {
            // Keep going with the current request rather than not running jobs.
        }

        $this->resetScope();
    }

    /**
     * Forget everything that belongs to the visitor or to the previous job:
     * the logged-in user, scoped services (incl. log context) and the locale.
     */
    protected function resetScope(): void
    {
        if ($this->app->resolved('auth')) {
            $this->app->make('auth')->forgetGuards();
        }

        if (method_exists($this->app, 'forgetScopedInstances')) {
            $this->app->forgetScopedInstances();
        }

        $locale = $this->config->get('app.locale');

        if (is_string($locale) && $locale !== '' && method_exists($this->app, 'setLocale')) {
            $this->app->setLocale($locale);
        }
    }

    /** After `migrate:fresh` the tables are gone but the "installed" mark stays: drop the mark. */
    protected function recoverMissingTables(): void
    {
        try {
            $schema = $this->app->make('db')
                ->connection($this->config->get('queue.connections.workerless.connection'))
                ->getSchemaBuilder();

            if (! $schema->hasTable((string) $this->config->get('queue.connections.workerless.table', 'jobs'))
                || ! $schema->hasTable(Installer::RUNS_TABLE)) {
                $this->installer->forget();
            }
        } catch (Throwable) {
            // Database unreachable: nothing to recover here.
        }
    }

    /** True while this request runs jobs (used to log failed jobs, see the service provider). */
    public function isProcessing(): bool
    {
        return $this->processing;
    }

    protected function work(): void
    {
        $this->processing = true;

        try {
            $this->workLoop();
        } finally {
            $this->processing = false;
        }
    }

    protected function workLoop(): void
    {
        $queue = $this->app->make('queue')->connection(self::CONNECTION);

        /** @var Worker $worker */
        $worker = $this->app->make('queue.worker');

        $options = new WorkerOptions();
        $options->sleep = 0;

        $maxSeconds = max(1, (int) $this->config->get('workerless.runner.max_seconds', 30));
        $maxJobs = max(1, (int) $this->config->get('workerless.runner.max_jobs', 10));
        $retryAfter = $this->retryAfter();
        $queues = $this->queues();

        $deadline = microtime(true) + $maxSeconds;
        $done = 0;

        while ($done < $maxJobs && microtime(true) < $deadline) {
            $job = null;

            foreach ($queues as $name) {
                if ($job = $queue->pop($name)) {
                    break;
                }
            }

            if ($job === null) {
                break;
            }

            // Each job starts clean, never seeing what the previous one left behind.
            $this->resetScope();

            // A job must end before retry_after, or another run could pick it up again.
            if (function_exists('set_time_limit')) {
                @set_time_limit($retryAfter);
            }

            try {
                $worker->process(self::CONNECTION, $job, $options);
            } catch (Throwable $e) {
                // The Worker already released or failed the job; just report it.
                $this->report($e);
            }

            $done++;
        }
    }

    /** Store when the next job becomes ready, so idle requests skip the database. */
    protected function refreshNextDue(): void
    {
        $connection = $this->app->make('db')->connection($this->config->get('queue.connections.workerless.connection'));
        $table = (string) $this->config->get('queue.connections.workerless.table', 'jobs');
        $queues = $this->queues();

        $waiting = $connection->table($table)
            ->whereIn('queue', $queues)
            ->whereNull('reserved_at')
            ->min('available_at');

        $reserved = $connection->table($table)
            ->whereIn('queue', $queues)
            ->whereNotNull('reserved_at')
            ->min('reserved_at');

        $candidates = [];

        if ($waiting !== null) {
            $candidates[] = (int) $waiting;
        }

        if ($reserved !== null) {
            $candidates[] = (int) $reserved + $this->retryAfter();
        }

        $this->state->write('next-due', $candidates === [] ? PHP_INT_MAX : min($candidates));
    }

    /** @return array<int, string> */
    protected function queues(): array
    {
        $queues = $this->config->get('workerless.runner.queues')
            ?? $this->config->get('queue.connections.workerless.queue', 'default');

        if (is_string($queues)) {
            $queues = explode(',', $queues);
        }

        $queues = array_values(array_filter(array_map('trim', (array) $queues), fn ($q) => $q !== ''));

        return $queues === [] ? ['default'] : $queues;
    }

    protected function retryAfter(): int
    {
        return max(10, (int) $this->config->get('queue.connections.workerless.retry_after', 90));
    }

    protected function report(Throwable $e): void
    {
        try {
            $this->app->make(ExceptionHandler::class)->report($e);
        } catch (Throwable) {
            // Reporting must never break the request.
        }
    }
}
