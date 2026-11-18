<?php

declare(strict_types=1);

namespace HimaCode\Workerless;

use HimaCode\Workerless\History\Recorder;
use HimaCode\Workerless\Http\RunAfterResponse;
use HimaCode\Workerless\Loopback\Signer;
use HimaCode\Workerless\Loopback\Trigger;
use HimaCode\Workerless\Queue\WorkerlessConnector;
use HimaCode\Workerless\Runner\Runner;
use HimaCode\Workerless\Support\EnvWriter;
use HimaCode\Workerless\Support\Installer;
use HimaCode\Workerless\Support\State;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;

/**
 * Entry point of Workerless inside a Laravel application.
 */
class WorkerlessServiceProvider extends ServiceProvider
{
    public const VERSION = '1.0.0';

    /** Documentation and source, linked from the dashboard. */
    public const REPOSITORY = 'https://github.com/himacode-company/workerless';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workerless.php', 'workerless');

        $this->registerQueueConnection();

        $this->app->singleton(State::class, fn ($app) => new State($app->storagePath('framework/workerless')));
        $this->app->singleton(Installer::class);
        $this->app->singleton(Runner::class);
        $this->app->singleton(Signer::class);
        $this->app->singleton(Trigger::class);
        $this->app->singleton(Recorder::class);
    }

    public function boot(): void
    {
        $this->callAfterResolving('queue', function (QueueManager $manager): void {
            $manager->addConnector(Runner::CONNECTION, fn () => new WorkerlessConnector(
                $this->app['db'],
                $this->app->make(Installer::class),
            ));
        });

        // A new job was stored: the next request may run it.
        $this->app['events']->listen(JobQueued::class, function (JobQueued $event): void {
            if ($event->connectionName === Runner::CONNECTION) {
                $this->app->make(State::class)->noteDueAt(time());
            }
        });

        // Store failed jobs in failed_jobs. Laravel does this only inside `queue:work`
        // (WorkCommand::logFailedJob), so the runner must do it itself. Only while
        // Workerless runs the job, so a real queue:work never logs it twice.
        $this->app['events']->listen(JobFailed::class, function (JobFailed $event): void {
            if ($event->connectionName !== Runner::CONNECTION || ! $this->app->make(Runner::class)->isProcessing()) {
                return;
            }

            try {
                $this->app['queue.failer']->log(
                    $event->connectionName,
                    $event->job->getQueue(),
                    $event->job->getRawBody(),
                    $event->exception,
                );
            } catch (\Throwable $e) {
                report($e);
            }
        });

        // History of finished jobs for the dashboard.
        $this->app->make(Recorder::class)->subscribe($this->app['events']);

        $this->loadRoutesFrom(__DIR__ . '/../routes/workerless.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'workerless');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->bound(HttpKernel::class)) {
            $kernel = $this->app->make(HttpKernel::class);

            if (method_exists($kernel, 'pushMiddleware')) {
                $kernel->pushMiddleware(RunAfterResponse::class);
            }
        }

        if ($this->app->runningInConsole()) {
            // Right after `composer require`: show the developer what to fill in
            // .env, and put config/workerless.php in the app, ready to edit.
            if (in_array('package:discover', (array) ($_SERVER['argv'] ?? []), true)) {
                (new EnvWriter($this->app->basePath()))->addMissingKeys();
                $this->publishConfigIfMissing();
            }

            $this->publishes([
                __DIR__ . '/../config/workerless.php' => $this->app->configPath('workerless.php'),
            ], 'workerless-config');
        }
    }

    /**
     * Copies the config file into the app's config folder on install, so it
     * can be edited right away. An existing file is never overwritten.
     */
    protected function publishConfigIfMissing(): void
    {
        $target = $this->app->configPath('workerless.php');
        $folder = dirname($target);

        if (is_file($target) || ! is_dir($folder) || ! is_writable($folder)) {
            return;
        }

        @copy(__DIR__ . '/../config/workerless.php', $target);
    }

    /**
     * Adds `queue.connections.workerless`, so QUEUE_CONNECTION=workerless works
     * without editing config/queue.php.
     *
     * Priority: the app's own `workerless` connection, then the app's `database`
     * connection (same table and DB), then Laravel's defaults. No env() here,
     * so it stays correct after `php artisan config:cache`.
     */
    protected function registerQueueConnection(): void
    {
        $config = $this->app['config'];

        $database = (array) $config->get('queue.connections.database', []);
        unset($database['driver']);

        $config->set('queue.connections.workerless', array_merge(
            [
                'connection' => null,
                'table' => 'jobs',
                'queue' => 'default',
                'retry_after' => 90,
                'after_commit' => false,
            ],
            $database,
            (array) $config->get('queue.connections.workerless', []),
            ['driver' => 'workerless'],
        ));
    }
}
