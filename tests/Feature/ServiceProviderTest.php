<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Feature;

use HimaCode\Workerless\Queue\WorkerlessQueue;
use HimaCode\Workerless\Tests\TestCase;
use HimaCode\Workerless\WorkerlessServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_the_service_provider_is_loaded(): void
    {
        $this->assertArrayHasKey(WorkerlessServiceProvider::class, $this->app->getLoadedProviders());
    }

    public function test_the_workerless_connection_registers_itself(): void
    {
        $this->assertSame('workerless', config('queue.connections.workerless.driver'));
        $this->assertInstanceOf(WorkerlessQueue::class, $this->app['queue']->connection('workerless'));
    }

    public function test_the_config_has_safe_defaults(): void
    {
        $this->assertTrue(config('workerless.enabled'));
        $this->assertSame(10, config('workerless.runner.max_jobs'));
        $this->assertSame(30, config('workerless.runner.max_seconds'));
        $this->assertSame(30, config('workerless.loopback.max_wait'));
        $this->assertSame(300, config('workerless.loopback.horizon'));
        $this->assertTrue(config('workerless.loopback.verify_ssl'));
        $this->assertSame('workerless', config('workerless.dashboard.path'));
        $this->assertSame(7, config('workerless.history.keep_days'));
    }
}
