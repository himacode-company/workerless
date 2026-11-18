<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Feature;

use HimaCode\Workerless\Tests\Fixtures\FailingJob;
use HimaCode\Workerless\Tests\Fixtures\RecordingJob;
use HimaCode\Workerless\Tests\Fixtures\WhoAmIJob;
use HimaCode\Workerless\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;

class RunnerTest extends TestCase
{
    public function test_a_dispatched_job_is_stored_then_run(): void
    {
        RecordingJob::dispatch('first');

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame([], RecordingJob::$handled);

        $this->runner()->run();

        $this->assertSame(['first'], RecordingJob::$handled);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_a_finished_job_is_written_to_the_history(): void
    {
        RecordingJob::dispatch();

        $this->runner()->run();

        $run = DB::table('workerless_runs')->first();

        $this->assertNotNull($run);
        $this->assertSame('succeeded', $run->status);
        $this->assertSame(RecordingJob::class, $run->name);
        $this->assertNull($run->error);
    }

    public function test_several_jobs_run_in_one_run(): void
    {
        foreach (['a', 'b', 'c'] as $label) {
            RecordingJob::dispatch($label);
        }

        $this->runner()->run();

        $this->assertSame(['a', 'b', 'c'], RecordingJob::$handled);
    }

    public function test_one_run_never_takes_more_than_max_jobs(): void
    {
        config()->set('workerless.runner.max_jobs', 2);

        foreach (['a', 'b', 'c'] as $label) {
            RecordingJob::dispatch($label);
        }

        $this->runner()->run();

        $this->assertSame(['a', 'b'], RecordingJob::$handled);
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_a_delayed_job_waits_for_its_time(): void
    {
        RecordingJob::dispatch('later')->delay(now()->addMinutes(10));

        $this->runner()->run();

        $this->assertSame([], RecordingJob::$handled);
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_a_failing_job_is_retried_then_stored_in_failed_jobs(): void
    {
        FailingJob::dispatch();

        $this->runner()->run();

        $this->assertSame(2, FailingJob::$attempts);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('failed_jobs')->count());

        $statuses = DB::table('workerless_runs')->orderBy('id')->pluck('status')->all();
        $this->assertSame(['released', 'failed'], $statuses);
    }

    public function test_a_job_never_sees_the_visitor_who_was_logged_in(): void
    {
        $this->actingAs(new GenericUser(['id' => 42, 'name' => 'Visitor']));
        $this->assertSame(42, auth()->id());

        WhoAmIJob::dispatch();
        $this->runner()->run();

        $this->assertSame('guest', WhoAmIJob::$seen);
    }

    public function test_a_new_job_wakes_the_runner_up(): void
    {
        config()->set('workerless.runner.cooldown', 0);

        $this->runner()->run(); // empty queue: the next run is far away
        $this->assertFalse($this->runner()->shouldRun());

        RecordingJob::dispatch();

        $this->assertTrue($this->runner()->shouldRun());
    }

    public function test_monitoring_mode_never_runs_jobs(): void
    {
        config()->set('workerless.enabled', false);

        RecordingJob::dispatch();

        $this->assertFalse($this->runner()->shouldRun());
    }
}
