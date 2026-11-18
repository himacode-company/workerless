<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests;

use HimaCode\Workerless\Runner\Runner;
use HimaCode\Workerless\Tests\Fixtures\FailingJob;
use HimaCode\Workerless\Tests\Fixtures\RecordingJob;
use HimaCode\Workerless\Tests\Fixtures\WhoAmIJob;
use HimaCode\Workerless\WorkerlessServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * A fresh Laravel app per test: SQLite in memory, Workerless as the default
 * queue, and no real self-calls (no network traffic during tests).
 */
abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // State files outlive the in-memory database: start every test clean.
        File::deleteDirectory($this->app->storagePath('framework/workerless'));

        RecordingJob::$handled = [];
        FailingJob::$attempts = 0;
        WhoAmIJob::$seen = 'not run';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->storagePath('framework/workerless'));

        parent::tearDown();
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [WorkerlessServiceProvider::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('app.key', 'base64:' . base64_encode(str_repeat('w', 32)));
        $config->set('app.url', 'http://localhost');

        $config->set('database.default', 'testing');

        $config->set('queue.default', 'workerless');
        $config->set('queue.failed.driver', 'database-uuids');
        $config->set('queue.failed.database', 'testing');
        $config->set('queue.failed.table', 'failed_jobs');

        // No real HTTP self-calls in tests. Tests that need them turn this on.
        $config->set('workerless.loopback.enabled', false);
    }

    protected function runner(): Runner
    {
        return $this->app->make(Runner::class);
    }
}
