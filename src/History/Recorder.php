<?php

declare(strict_types=1);

namespace HimaCode\Workerless\History;

use HimaCode\Workerless\Runner\Runner;
use HimaCode\Workerless\Support\Installer;
use HimaCode\Workerless\Support\State;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes one row to `workerless_runs` for every finished job of the workerless
 * connection: succeeded, failed, or released for a retry.
 *
 * Never stores the job payload: only the class name, queue, attempts,
 * duration and a short, single-line error message.
 * Recording can never break a job: every write is wrapped.
 */
class Recorder
{
    /** @var array<int, float> start time per job object */
    protected array $started = [];

    /** @var array<int, Throwable> last exception per job object */
    protected array $errors = [];

    public function __construct(
        protected DatabaseManager $db,
        protected Config $config,
        protected State $state,
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(JobProcessing::class, function (JobProcessing $event): void {
            if ($event->connectionName === Runner::CONNECTION) {
                $this->started[spl_object_id($event->job)] = microtime(true);
            }
        });

        $events->listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
            if ($event->connectionName === Runner::CONNECTION) {
                $this->errors[spl_object_id($event->job)] = $event->exception;
            }
        });

        $events->listen(JobProcessed::class, function (JobProcessed $event): void {
            $this->finish($event->connectionName, $event->job, 'succeeded');
        });

        $events->listen(JobFailed::class, function (JobFailed $event): void {
            $this->finish($event->connectionName, $event->job, 'failed', $event->exception);
        });

        $events->listen(JobReleasedAfterException::class, function (JobReleasedAfterException $event): void {
            $this->finish($event->connectionName, $event->job, 'released');
        });
    }

    /** Deletes history older than `history.keep_days`. At most once an hour. */
    public function prune(): void
    {
        $last = $this->state->read('last-prune');

        if ($last !== null && time() - $last < 3600) {
            return;
        }

        $this->state->write('last-prune', time());

        $days = max(1, (int) $this->config->get('workerless.history.keep_days', 7));

        try {
            $this->connection()->table(Installer::RUNS_TABLE)
                ->where('created_at', '<', now()->subDays($days))
                ->delete();
        } catch (Throwable) {
            // History is a nice-to-have; never fail because of it.
        }
    }

    protected function finish(string $connection, Job $job, string $status, ?Throwable $exception = null): void
    {
        if ($connection !== Runner::CONNECTION) {
            return;
        }

        $key = spl_object_id($job);
        $started = $this->started[$key] ?? null;
        $exception ??= $this->errors[$key] ?? null;

        unset($this->started[$key], $this->errors[$key]);

        try {
            $payload = $job->payload();

            $this->connection()->table(Installer::RUNS_TABLE)->insert([
                'job_uuid' => is_string($payload['uuid'] ?? null) ? Str::limit($payload['uuid'], 64, '') : null,
                'name' => Str::limit($job->resolveName(), 250, ''),
                'queue' => Str::limit((string) $job->getQueue(), 250, ''),
                'status' => $status,
                'attempts' => min(65535, max(0, $job->attempts())),
                'duration_ms' => $started === null ? null : (int) round((microtime(true) - $started) * 1000),
                'error' => $exception === null ? null : $this->describe($exception),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // History is a nice-to-have; never fail a job because of it.
        }
    }

    /** "ClassName: first line of the message", max 500 chars. */
    protected function describe(Throwable $e): string
    {
        $message = trim((string) strtok($e->getMessage(), "\r\n"));

        return Str::limit(class_basename($e) . ($message === '' ? '' : ': ' . $message), 500);
    }

    protected function connection(): \Illuminate\Database\Connection
    {
        return $this->db->connection($this->config->get('queue.connections.workerless.connection'));
    }
}
