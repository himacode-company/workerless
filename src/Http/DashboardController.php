<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Http;

use HimaCode\Workerless\Dashboard\Diagnostics;
use HimaCode\Workerless\Dashboard\HealthCheck;
use HimaCode\Workerless\Support\ResponseFinisher;
use HimaCode\Workerless\Dashboard\Metrics;
use HimaCode\Workerless\Support\State;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DashboardController
{
    public const TABS = ['overview', 'jobs', 'failed', 'history', 'health'];

    /** Simple: one page with what matters. Advanced: every tab. */
    public const MODES = ['simple', 'advanced'];

    /** Failed job ids are numbers or UUIDs. Anything else is rejected. */
    protected const ID_PATTERN = '/^[A-Za-z0-9\-]{1,64}$/';

    public function index(Request $request, Metrics $metrics, HealthCheck $health, Diagnostics $diagnostics): Response
    {
        $mode = $this->mode($request);
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'overview';

        if ($mode === 'simple') {
            $tab = 'simple';
        }

        // Arrived from "Run diagnostics": record when, to know if the browser had to wait.
        $diag = $request->query('diag');

        if (is_string($diag) && preg_match('/^[a-f0-9]{16}$/', $diag) === 1) {
            $diagnostics->markArrived($diag);
        }

        $summary = $metrics->summary();
        $checks = $health->run($summary);

        $data = [
            'mode' => $mode,
            'tab' => $tab,
            'summary' => $summary,
            'checks' => $checks,
            'health' => HealthCheck::worst($checks),
            'server' => HealthCheck::serverMethod(),
            'diagnostic' => $diagnostics->result(),
            'error' => null,
        ];

        try {
            $data += match ($tab) {
                'simple' => ['failed' => $metrics->failedPage()],
                'overview' => ['hourly' => $metrics->hourly(), 'recent' => $metrics->recent()],
                'jobs' => ['jobs' => $metrics->jobsPage()],
                'failed' => ['failed' => $metrics->failedPage()],
                'history' => [
                    'status' => in_array($request->query('status'), Metrics::HISTORY_STATUSES, true) ? (string) $request->query('status') : null,
                    'runs' => $metrics->historyPage($request->query('status') === null ? null : (string) $request->query('status')),
                ],
                'health' => ['environment' => $this->environment($metrics)],
            };
        } catch (Throwable) {
            $data['error'] = 'This section could not be loaded. Check the Health tab.';
        }

        return response()
            ->view('workerless::dashboard', $data)
            ->withHeaders($this->securityHeaders());
    }

    public function retry(string $id, Artisan $artisan, State $state): RedirectResponse
    {
        abort_unless(preg_match(self::ID_PATTERN, $id) === 1, 404);

        return $this->runRetry($artisan, $state, [$id], 'The job was sent back to the queue.');
    }

    public function retryAll(Artisan $artisan, State $state): RedirectResponse
    {
        return $this->runRetry($artisan, $state, ['all'], 'All failed jobs were sent back to the queue.');
    }

    public function forget(string $id, FailedJobProviderInterface $failer): RedirectResponse
    {
        abort_unless(preg_match(self::ID_PATTERN, $id) === 1, 404);

        $deleted = (bool) $failer->forget($id);

        return $this->back($deleted ? 'The failed job was deleted.' : 'This job no longer exists.', $deleted ? 'ok' : 'warn');
    }

    /** Switches between Simple and Advanced. Remembered in the session. */
    public function setMode(Request $request): RedirectResponse
    {
        $mode = (string) $request->input('mode', '');

        abort_unless(in_array($mode, self::MODES, true), 404);

        $request->session()->put('workerless.mode', $mode);

        return redirect()->route('workerless.index');
    }

    protected function mode(Request $request): string
    {
        $mode = $request->hasSession() ? $request->session()->get('workerless.mode') : null;

        return in_array($mode, self::MODES, true) ? $mode : 'simple';
    }

    /**
     * Starts the hosting check. The redirect is sent first; the test work runs
     * after it (see Diagnostics). The next page measures if the browser waited.
     */
    public function runDiagnostics(Request $request, Diagnostics $diagnostics): RedirectResponse
    {
        if ($diagnostics->isRunning()) {
            return redirect()->route('workerless.index', ['tab' => 'health']);
        }

        $id = $diagnostics->start();
        $response = redirect()->route('workerless.index', ['tab' => 'health', 'diag' => $id]);

        ResponseFinisher::prepare($request, $response);

        app()->terminating(function () use ($diagnostics, $id): void {
            ResponseFinisher::finish();
            $diagnostics->background($id);
        });

        return $response;
    }

    /** Clears the history (workerless_runs only, never failed_jobs). */
    public function clearHistory(Request $request, Metrics $metrics): RedirectResponse
    {
        $scope = (string) $request->input('scope', '');

        abort_unless(in_array($scope, ['succeeded', 'failed', 'all'], true), 404);

        try {
            $count = $metrics->clearHistory($scope === 'all' ? null : $scope);
        } catch (Throwable) {
            return $this->back('The history could not be cleared. See the application log.', 'fail', 'history');
        }

        $what = $scope === 'all' ? 'history entries' : $scope . ' entries';

        return $this->back(number_format($count) . ' ' . ($count === 1 ? rtrim($what, 's') : $what) . ' deleted.', 'ok', 'history');
    }

    /** @param  array<int, string>  $ids */
    protected function runRetry(Artisan $artisan, State $state, array $ids, string $message): RedirectResponse
    {
        try {
            $artisan->call('queue:retry', ['id' => $ids]);
        } catch (Throwable) {
            return $this->back('The job could not be retried. See the application log.', 'fail');
        }

        // queue:retry does not fire JobQueued: wake the runner ourselves.
        $state->noteDueAt(time());

        return $this->back($message, 'ok');
    }

    protected function back(string $message, string $level, string $tab = 'failed'): RedirectResponse
    {
        return redirect()
            ->route('workerless.index', ['tab' => $tab])
            ->with('workerless.flash', ['message' => $message, 'level' => $level]);
    }

    /** @return array<string, string> */
    protected function environment(Metrics $metrics): array
    {
        return [
            'Workerless' => \HimaCode\Workerless\WorkerlessServiceProvider::VERSION,
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION . ' (' . PHP_SAPI . ')',
            'Server' => HealthCheck::serverMethod(),
            'max_execution_time' => ((int) ini_get('max_execution_time')) === 0 ? 'unlimited' : ini_get('max_execution_time') . ' s',
            'memory_limit' => (string) ini_get('memory_limit'),
            'retry_after' => $metrics->retryAfter() . ' s',
            'Per run' => config('workerless.runner.max_jobs') . ' jobs / ' . config('workerless.runner.max_seconds') . ' s',
            'Cooldown' => config('workerless.runner.cooldown') . ' s',
            'Mode' => config('workerless.enabled', true) ? 'Runs jobs' : 'Monitoring',
            'Self-calls' => config('workerless.loopback.enabled', true)
                ? 'wait ' . config('workerless.loopback.max_wait', 30) . ' s, look ahead ' . config('workerless.loopback.horizon', 300) . ' s'
                : 'disabled',
            'History kept' => config('workerless.history.keep_days') . ' days',
        ];
    }

    /** @return array<string, string> */
    protected function securityHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, private',
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }
}
