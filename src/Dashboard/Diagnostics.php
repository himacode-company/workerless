<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Dashboard;

use HimaCode\Workerless\Loopback\Trigger;
use HimaCode\Workerless\Support\State;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Real hosting check, run from the dashboard. It does what the old probe file
 * did, inside the package:
 *
 *  1. finish   - the "Run diagnostics" response is sent, then 2 s of work start.
 *                If the next page arrives in under 1.5 s, visitors never wait.
 *  2. survive  - the work writes a mark when it ends: PHP is not killed after the response.
 *  3. loopback - a signed self-call to a probe-only path must arrive (127.0.0.1, then public URL).
 *
 * Results live in storage/framework/workerless/diagnostics.json.
 */
class Diagnostics
{
    protected const FILE = 'diagnostics';

    /** Seconds of work done after the response. */
    protected const WORK_SECONDS = 2;

    /** A run that has not finished after this many seconds is judged as it is. */
    protected const TIMEOUT = 20;

    public function __construct(
        protected State $state,
        protected Trigger $trigger,
        protected Config $config,
    ) {
    }

    public function isRunning(): bool
    {
        $data = $this->state->readJson(self::FILE);

        return $data !== null && ! isset($data['finished']) && microtime(true) - (float) ($data['started'] ?? 0) < self::TIMEOUT;
    }

    public function start(): string
    {
        $id = bin2hex(random_bytes(8));

        $this->state->writeJson(self::FILE, [
            'id' => $id,
            'started' => microtime(true),
            'server' => HealthCheck::serverMethod(),
        ]);

        return $id;
    }

    /** Runs after the response was sent. */
    public function background(string $id): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }

        $this->update($id, ['work_started' => microtime(true)]);
        sleep(self::WORK_SECONDS);
        $this->update($id, ['work_done' => microtime(true)]);

        if (! $this->config->get('workerless.loopback.enabled', true)) {
            $this->update($id, ['loopback_disabled' => true, 'finished' => microtime(true)]);

            return;
        }

        foreach (['local' => true, 'public' => false] as $via => $local) {
            $this->trigger->probe($id, $local);

            if ($this->waitFor($id, 'loopback_received', 3.0)) {
                $this->update($id, ['loopback_via' => $via]);
                break;
            }
        }

        $this->update($id, ['finished' => microtime(true)]);
    }

    /** The page after "Run diagnostics" arrived: measures whether the browser had to wait. */
    public function markArrived(string $id): void
    {
        $data = $this->state->readJson(self::FILE);

        if (($data['id'] ?? null) === $id && ! isset($data['arrived'])) {
            $this->update($id, ['arrived' => microtime(true)]);
        }
    }

    /** Called by the signed probe self-call. */
    public function markLoopback(string $id): void
    {
        $this->update($id, ['loopback_received' => microtime(true)]);
    }

    /**
     * @return array{verdict: string, running: bool, checked_at: int, reason: string|null, checks: array<int, array{level: string, title: string, detail: string}>}|null
     */
    public function result(): ?array
    {
        $d = $this->state->readJson(self::FILE);

        if ($d === null || ! isset($d['started'])) {
            return null;
        }

        $running = $this->isRunning();
        $checks = [];

        // 1. Did the visitor wait?
        if (isset($d['arrived'])) {
            $waited = (float) $d['arrived'] - (float) $d['started'];
            $checks[] = $waited < 1.5
                ? $this->check('ok', 'Visitors never wait', 'The page arrived in ' . $this->ms($waited) . ' while work ran in the background.')
                : $this->check('fail', 'Visitors wait for the jobs', 'This server holds the page until background work ends (' . $this->ms($waited) . '). Every visitor would wait for your jobs.');
        } else {
            $checks[] = $running
                ? $this->check('pending', 'Visitors never wait', 'Measuring...')
                : $this->check('warn', 'Response timing not measured', 'Run the check again and stay on this page until it finishes.');
        }

        // 2. Does work survive after the response?
        if (isset($d['work_done'])) {
            $checks[] = $this->check('ok', 'Work keeps running after the page', 'Background work finished on its own after the response.');
        } elseif ($running) {
            $checks[] = $this->check('pending', 'Work keeps running after the page', 'Measuring...');
        } else {
            $checks[] = $this->check('fail', 'The server stops PHP after the page', 'Work started after the response never finished. This hosting kills PHP once the page is sent, so jobs cannot run here.');
        }

        // 3. Can the site call itself?
        if (! empty($d['loopback_disabled'])) {
            $checks[] = $this->check('warn', 'Self-calls are turned off', 'WORKERLESS_LOOPBACK=false: delayed jobs and retries wait for the next visitor.');
        } elseif (isset($d['loopback_received'])) {
            $checks[] = $this->check('ok', 'The site can call itself', 'Signed self-call arrived via ' . (($d['loopback_via'] ?? 'local') === 'local' ? '127.0.0.1' : 'the public address') . '. Delayed jobs and retries run without visitors.');
        } elseif ($running) {
            $checks[] = $this->check('pending', 'The site can call itself', 'Measuring...');
        } else {
            $checks[] = $this->check('warn', 'Self-calls are blocked', 'The site could not reach itself. Delayed jobs and retries will wait for the next visitor. Check that APP_URL is your real address and that a firewall or CDN does not block requests from the server itself.');
        }

        $levels = array_column($checks, 'level');
        $verdict = match (true) {
            $running && in_array('pending', $levels, true) => 'running',
            in_array('fail', $levels, true) => 'fail',
            in_array('warn', $levels, true) => 'warn',
            default => 'ok',
        };

        $reason = null;

        foreach (['fail', 'warn'] as $level) {
            foreach ($checks as $check) {
                if ($reason === null && $check['level'] === $level) {
                    $reason = $check['detail'];
                }
            }
        }

        return [
            'verdict' => $verdict,
            'running' => $verdict === 'running',
            'checked_at' => (int) $d['started'],
            'reason' => $reason,
            'checks' => $checks,
        ];
    }

    /** @param  array<string, mixed>  $changes */
    protected function update(string $id, array $changes): void
    {
        $data = $this->state->readJson(self::FILE);

        if (($data['id'] ?? null) !== $id) {
            return; // a newer run replaced this one
        }

        $this->state->writeJson(self::FILE, array_merge($data, $changes));
    }

    protected function waitFor(string $id, string $key, float $seconds): bool
    {
        $end = microtime(true) + $seconds;

        do {
            $data = $this->state->readJson(self::FILE);

            if (($data['id'] ?? null) !== $id) {
                return false;
            }

            if (isset($data[$key])) {
                return true;
            }

            usleep(100000);
        } while (microtime(true) < $end);

        return false;
    }

    /** @return array{level: string, title: string, detail: string} */
    protected function check(string $level, string $title, string $detail): array
    {
        return ['level' => $level, 'title' => $title, 'detail' => $detail];
    }

    protected function ms(float $seconds): string
    {
        return $seconds < 1 ? (int) round($seconds * 1000) . ' ms' : number_format($seconds, 1) . ' s';
    }
}
