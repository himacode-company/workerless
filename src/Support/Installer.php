<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Support;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Throwable;

/**
 * Creates the tables Workerless needs, so no `php artisan migrate` (and no SSH)
 * is required. Runs once: afterwards a marker file skips it entirely.
 *
 * Existing tables are never touched. Table layouts match Laravel's own
 * `jobs` and `failed_jobs` migrations.
 */
class Installer
{
    /** Bump when the schema changes, to run the installer again. */
    public const MARKER = 'installed-v1';

    public const RUNS_TABLE = 'workerless_runs';

    public function __construct(
        protected DatabaseManager $db,
        protected Config $config,
        protected State $state,
    ) {
    }

    /** Creates the missing tables once; afterwards a file check only. */
    public function ensure(): void
    {
        if ($this->state->has(self::MARKER)) {
            return;
        }

        $this->createTables();

        $this->state->write(self::MARKER, time());
    }

    /** Next ensure() checks the database again (e.g. after `migrate:fresh`). */
    public function forget(): void
    {
        $this->state->forget(self::MARKER);
    }

    /** Creates every missing table. Never touches an existing one. Also used by the package migration. */
    public function createTables(): void
    {
        $jobs = $this->db->connection($this->config->get('queue.connections.workerless.connection'));

        $this->createIfMissing($jobs, (string) $this->config->get('queue.connections.workerless.table', 'jobs'), function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $this->createIfMissing($jobs, self::RUNS_TABLE, function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('job_uuid', 64)->nullable()->index();
            $table->string('name');
            $table->string('queue');
            $table->string('status', 16)->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        $driver = $this->config->get('queue.failed.driver', 'database-uuids');

        if (in_array($driver, ['database', 'database-uuids'], true)) {
            $failed = $this->db->connection($this->config->get('queue.failed.database'));
            $withUuid = $driver === 'database-uuids';

            $this->createIfMissing($failed, (string) $this->config->get('queue.failed.table', 'failed_jobs'), function (Blueprint $table) use ($withUuid): void {
                $table->bigIncrements('id');
                if ($withUuid) {
                    $table->string('uuid')->unique();
                }
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    protected function createIfMissing(Connection $connection, string $table, Closure $schema): void
    {
        $builder = $connection->getSchemaBuilder();

        if ($builder->hasTable($table)) {
            return;
        }

        try {
            $builder->create($table, $schema);
        } catch (Throwable $e) {
            // Another request may have created it at the same moment.
            if (! $builder->hasTable($table)) {
                throw $e;
            }
        }
    }
}
