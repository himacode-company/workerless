<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Dashboard;

use Closure;
use HimaCode\Workerless\Support\Installer;
use HimaCode\Workerless\Support\State;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Numbers and lists shown on the dashboard. Read-only.
 * Job payloads are decoded only to read the job's display name.
 */
class Metrics
{
    public const PER_PAGE = 25;

    public const HISTORY_STATUSES = ['succeeded', 'failed', 'released'];

    public function __construct(
        protected DatabaseManager $db,
        protected Config $config,
        protected State $state,
    ) {
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $now = time();
        $retryAfter = $this->retryAfter();

        $summary = [
            'pending' => null,
            'delayed' => null,
            'running' => null,
            'stuck' => null,
            'oldest_pending' => null,
            'failed' => null,
            'succeeded_24h' => null,
            'failed_24h' => null,
            'released_24h' => null,
            'avg_ms' => null,
            'last_run' => $this->state->read('last-run'),
            'last_check' => $this->state->read('last-check'),
            'last_tick' => $this->state->read('last-tick'),
            'next_due' => $this->state->read('next-due'),
        ];

        $summary = array_merge($summary, $this->safe(fn () => [
            'pending' => $this->jobs()->whereNull('reserved_at')->where('available_at', '<=', $now)->count(),
            'delayed' => $this->jobs()->whereNull('reserved_at')->where('available_at', '>', $now)->count(),
            'running' => $this->jobs()->whereNotNull('reserved_at')->where('reserved_at', '>', $now - $retryAfter)->count(),
            'stuck' => $this->jobs()->whereNotNull('reserved_at')->where('reserved_at', '<=', $now - $retryAfter)->count(),
            'oldest_pending' => $this->jobs()->whereNull('reserved_at')->where('available_at', '<=', $now)->min('available_at'),
        ]));

        $summary = array_merge($summary, $this->safe(function () {
            $failed = $this->failedJobs();

            return $failed === null ? [] : ['failed' => $failed->count()];
        }));

        return array_merge($summary, $this->safe(function () {
            $rows = $this->runs()
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw('status, count(*) as total, avg(duration_ms) as avg_ms')
                ->groupBy('status')
                ->get();

            $out = ['succeeded_24h' => 0, 'failed_24h' => 0, 'released_24h' => 0];

            foreach ($rows as $row) {
                if (in_array($row->status, self::HISTORY_STATUSES, true)) {
                    $out[$row->status . '_24h'] = (int) $row->total;
                }

                if ($row->status === 'succeeded' && $row->avg_ms !== null) {
                    $out['avg_ms'] = (int) round((float) $row->avg_ms);
                }
            }

            return $out;
        }));
    }

    /**
     * Jobs finished per hour over the last 24 hours.
     *
     * @return array<int, array{label: string, succeeded: int, failed: int}>
     */
    public function hourly(): array
    {
        $start = now()->startOfHour()->subHours(23);
        $buckets = [];

        for ($i = 0; $i < 24; $i++) {
            $buckets[$i] = ['label' => $start->copy()->addHours($i)->format('H:00'), 'succeeded' => 0, 'failed' => 0];
        }

        try {
            $rows = $this->runs()
                ->where('created_at', '>=', $start)
                ->whereIn('status', ['succeeded', 'failed'])
                ->select(['status', 'created_at'])
                ->cursor();

            foreach ($rows as $row) {
                $index = intdiv(Carbon::parse($row->created_at)->getTimestamp() - $start->getTimestamp(), 3600);

                if (isset($buckets[$index])) {
                    $buckets[$index][$row->status]++;
                }
            }
        } catch (Throwable) {
            // Empty chart.
        }

        return $buckets;
    }

    /** @return array<int, object> */
    public function recent(int $limit = 8): array
    {
        try {
            return $this->runs()->orderByDesc('id')->limit($limit)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function jobsPage(): Paginator
    {
        $now = time();
        $retryAfter = $this->retryAfter();

        return $this->jobs()
            ->orderByRaw('case when reserved_at is null then 1 else 0 end') // running first; portable SQL
            ->orderBy('available_at')
            ->orderBy('id')
            ->simplePaginate(self::PER_PAGE, ['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->withQueryString()
            ->through(function (object $row) use ($now, $retryAfter): array {
                $status = match (true) {
                    $row->reserved_at !== null && (int) $row->reserved_at <= $now - $retryAfter => 'stuck',
                    $row->reserved_at !== null => 'running',
                    (int) $row->available_at > $now => 'delayed',
                    default => 'pending',
                };

                return [
                    'id' => (int) $row->id,
                    'name' => $this->displayName($row->payload),
                    'queue' => (string) $row->queue,
                    'attempts' => (int) $row->attempts,
                    'status' => $status,
                    'available_at' => (int) $row->available_at,
                    'created_at' => (int) $row->created_at,
                ];
            });
    }

    public function failedPage(): ?Paginator
    {
        $query = $this->failedJobs();

        if ($query === null) {
            return null;
        }

        return $query
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->simplePaginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (object $row): array => [
                // queue:retry and the failer accept the uuid when it exists, else the id.
                'key' => (string) (isset($row->uuid) && $row->uuid !== '' ? $row->uuid : $row->id),
                'name' => $this->displayName($row->payload),
                'queue' => (string) $row->queue,
                'connection' => (string) $row->connection,
                'error' => $this->firstLine((string) $row->exception),
                'failed_at' => $row->failed_at,
            ]);
    }

    public function historyPage(?string $status): Paginator
    {
        return $this->runs()
            ->when(in_array($status, self::HISTORY_STATUSES, true), fn (Builder $q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->simplePaginate(self::PER_PAGE)
            ->withQueryString();
    }

    /** Deletes history rows: one status, or everything when $status is null. Returns the count. */
    public function clearHistory(?string $status): int
    {
        return $this->runs()
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->delete();
    }

    public function failedStoreIsDatabase(): bool
    {
        return in_array($this->config->get('queue.failed.driver', 'database-uuids'), ['database', 'database-uuids'], true);
    }

    public function retryAfter(): int
    {
        return max(10, (int) $this->config->get('queue.connections.workerless.retry_after', 90));
    }

    public function jobsConnection(): Connection
    {
        return $this->db->connection($this->config->get('queue.connections.workerless.connection'));
    }

    public function jobsTableName(): string
    {
        return (string) $this->config->get('queue.connections.workerless.table', 'jobs');
    }

    protected function jobs(): Builder
    {
        return $this->jobsConnection()->table($this->jobsTableName());
    }

    protected function runs(): Builder
    {
        return $this->jobsConnection()->table(Installer::RUNS_TABLE);
    }

    protected function failedJobs(): ?Builder
    {
        if (! $this->failedStoreIsDatabase()) {
            return null;
        }

        return $this->db->connection($this->config->get('queue.failed.database'))
            ->table((string) $this->config->get('queue.failed.table', 'failed_jobs'));
    }

    protected function displayName(mixed $payload): string
    {
        $data = is_string($payload) ? json_decode($payload, true) : null;
        $name = is_array($data) ? ($data['displayName'] ?? $data['job'] ?? null) : null;

        return is_string($name) && $name !== '' ? Str::limit($name, 200) : 'Unknown job';
    }

    protected function firstLine(string $text): string
    {
        return Str::limit(trim((string) strtok($text, "\r\n")), 300);
    }

    /**
     * @param  Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    protected function safe(Closure $callback): array
    {
        try {
            return $callback();
        } catch (Throwable) {
            return [];
        }
    }
}
