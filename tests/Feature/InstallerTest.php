<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Feature;

use HimaCode\Workerless\Support\Installer;
use HimaCode\Workerless\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class InstallerTest extends TestCase
{
    public function test_it_creates_every_table_it_needs(): void
    {
        $this->app->make(Installer::class)->ensure();

        foreach (['jobs', 'failed_jobs', 'workerless_runs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} was not created.");
        }
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $installer = $this->app->make(Installer::class);

        $installer->ensure();
        $installer->forget();
        $installer->ensure();

        $this->assertTrue(Schema::hasTable('jobs'));
    }

    public function test_the_first_dispatch_creates_the_tables(): void
    {
        $this->assertFalse(Schema::hasTable('jobs'));

        \HimaCode\Workerless\Tests\Fixtures\RecordingJob::dispatch();

        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('jobs')->count());
    }

    public function test_tables_come_back_after_they_were_dropped(): void
    {
        $this->app->make(Installer::class)->ensure();
        Schema::drop('jobs');

        // First run notices the missing table, second run recreates it.
        $this->runner()->run();
        $this->runner()->run();

        $this->assertTrue(Schema::hasTable('jobs'));
    }

    public function test_the_package_migration_works_with_artisan_migrate(): void
    {
        $this->artisan('migrate')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('workerless_runs'));
        $this->assertTrue(Schema::hasTable('jobs'));
    }
}
