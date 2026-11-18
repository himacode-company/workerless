<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Feature;

use HimaCode\Workerless\Dashboard\Diagnostics;
use HimaCode\Workerless\Support\State;
use HimaCode\Workerless\Tests\TestCase;

/** The verdict shown after "Check this hosting", from recorded results. */
class DiagnosticsTest extends TestCase
{
    public function test_no_check_yet_means_no_verdict(): void
    {
        $this->assertNull($this->app->make(Diagnostics::class)->result());
    }

    public function test_a_hosting_that_passes_everything_is_compatible(): void
    {
        $started = microtime(true) - 30;

        $this->record([
            'arrived' => $started + 0.3,
            'work_started' => $started + 0.1,
            'work_done' => $started + 2.1,
            'loopback_received' => $started + 2.3,
            'loopback_via' => 'local',
            'finished' => $started + 2.4,
        ], $started);

        $result = $this->app->make(Diagnostics::class)->result();

        $this->assertSame('ok', $result['verdict']);
        $this->assertNull($result['reason']);
    }

    public function test_a_hosting_that_kills_php_after_the_page_is_not_compatible(): void
    {
        $started = microtime(true) - 30;

        $this->record([
            'arrived' => $started + 0.2,
            'work_started' => $started + 0.1,
        ], $started);

        $result = $this->app->make(Diagnostics::class)->result();

        $this->assertSame('fail', $result['verdict']);
        $this->assertStringContainsString('kills PHP', (string) $result['reason']);
    }

    public function test_a_hosting_that_makes_visitors_wait_is_not_compatible(): void
    {
        $started = microtime(true) - 30;

        $this->record([
            'arrived' => $started + 2.6,
            'work_done' => $started + 2.1,
            'loopback_received' => $started + 2.3,
            'finished' => $started + 2.4,
        ], $started);

        $this->assertSame('fail', $this->app->make(Diagnostics::class)->result()['verdict']);
    }

    public function test_blocked_self_calls_mean_works_with_limits(): void
    {
        $started = microtime(true) - 30;

        $this->record([
            'arrived' => $started + 0.3,
            'work_done' => $started + 2.1,
            'finished' => $started + 8.5,
        ], $started);

        $result = $this->app->make(Diagnostics::class)->result();

        $this->assertSame('warn', $result['verdict']);
        $this->assertStringContainsString('could not reach itself', (string) $result['reason']);
    }

    /** @param  array<string, mixed>  $data */
    protected function record(array $data, float $started): void
    {
        $this->app->make(State::class)->writeJson('diagnostics', array_merge([
            'id' => '0123456789abcdef',
            'started' => $started,
            'server' => 'LiteSpeed',
        ], $data));
    }
}
