<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Feature;

use HimaCode\Workerless\Support\Installer;
use HimaCode\Workerless\Tests\Fixtures\FailingJob;
use HimaCode\Workerless\Tests\Fixtures\RecordingJob;
use HimaCode\Workerless\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Installer::class)->ensure();
    }

    // ---------- who can open it ----------

    public function test_a_guest_in_production_gets_a_404(): void
    {
        $this->environment('production');

        $this->get('/workerless')->assertNotFound();
    }

    public function test_a_listed_user_can_open_it_in_production(): void
    {
        $this->environment('production');
        config()->set('workerless.dashboard.user_ids', '7,42');

        $this->actingAs($this->user(42))->get('/workerless')->assertOk()->assertSee('Waiting');
    }

    public function test_a_user_who_is_not_listed_gets_a_404(): void
    {
        $this->environment('production');
        config()->set('workerless.dashboard.user_ids', '42');

        $this->actingAs($this->user(7))->get('/workerless')->assertNotFound();
    }

    public function test_local_opens_from_the_same_machine(): void
    {
        $this->environment('local');

        $this->get('/workerless')->assertOk();
    }

    public function test_local_is_closed_to_other_machines(): void
    {
        $this->environment('local');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get('/workerless')
            ->assertNotFound();
    }

    // ---------- pages ----------

    public function test_simple_mode_is_the_default(): void
    {
        RecordingJob::dispatch();

        $this->asAdmin()->get('/workerless')
            ->assertOk()
            ->assertSee('Waiting')
            ->assertSee('Failed jobs');
    }

    public function test_every_advanced_tab_renders(): void
    {
        RecordingJob::dispatch();
        FailingJob::dispatch();
        $this->runner()->run();
        RecordingJob::dispatch('waiting');

        foreach (['overview', 'jobs', 'failed', 'history', 'health'] as $tab) {
            $this->asAdmin()
                ->withSession(['workerless.mode' => 'advanced'])
                ->get('/workerless?tab=' . $tab)
                ->assertOk();
        }
    }

    public function test_the_mode_can_be_switched(): void
    {
        $this->asAdmin()
            ->postForm('/workerless/mode', ['mode' => 'advanced'])
            ->assertRedirect()
            ->assertSessionHas('workerless.mode', 'advanced');
    }

    // ---------- actions ----------

    public function test_a_failed_job_can_be_retried(): void
    {
        FailingJob::dispatch();
        $this->runner()->run();

        $uuid = (string) DB::table('failed_jobs')->value('uuid');

        $this->asAdmin()->postForm("/workerless/failed/{$uuid}/retry")->assertRedirect();

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_a_failed_job_can_be_deleted(): void
    {
        FailingJob::dispatch();
        $this->runner()->run();

        $uuid = (string) DB::table('failed_jobs')->value('uuid');

        $this->asAdmin()->postForm("/workerless/failed/{$uuid}/delete")->assertRedirect();

        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_the_history_can_be_cleared(): void
    {
        RecordingJob::dispatch();
        $this->runner()->run();
        $this->assertSame(1, DB::table('workerless_runs')->count());

        $this->asAdmin()->postForm('/workerless/history/clear', ['scope' => 'all'])->assertRedirect();

        $this->assertSame(0, DB::table('workerless_runs')->count());
    }

    public function test_actions_are_closed_to_guests(): void
    {
        $this->environment('production');

        // Even with a valid CSRF token, a guest never reaches the action.
        $this->postForm('/workerless/history/clear', ['scope' => 'all'])->assertNotFound();
    }

    public function test_actions_are_protected_by_csrf(): void
    {
        $this->asAdmin()->post('/workerless/history/clear', ['scope' => 'all'])->assertStatus(419);
    }

    // ---------- helpers ----------

    /**
     * POST like a real browser form: with the CSRF token (@csrf). Needed because
     * these tests switch the environment away from "testing", and Laravel only
     * skips the CSRF check in the "testing" environment.
     *
     * @param  array<string, mixed>  $data
     */
    protected function postForm(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['_token' => 'workerless-test-token'])
            ->post($uri, $data + ['_token' => 'workerless-test-token']);
    }

    protected function environment(string $name): void
    {
        $this->app['env'] = $name;
    }

    protected function user(int $id): GenericUser
    {
        return new GenericUser(['id' => $id, 'name' => 'User ' . $id]);
    }

    /** An allowed user, in production. */
    protected function asAdmin(): static
    {
        $this->environment('production');
        config()->set('workerless.dashboard.user_ids', '1');

        return $this->actingAs($this->user(1));
    }
}
