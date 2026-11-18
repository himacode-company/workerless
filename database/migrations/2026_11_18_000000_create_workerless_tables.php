<?php

use HimaCode\Workerless\Support\Installer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 * Workerless creates its tables by itself at the first job, so this migration
 * is not required. It exists so that `php artisan migrate` and `migrate:fresh`
 * know about the tables too. Existing tables are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(Installer::class)->createTables();
    }

    public function down(): void
    {
        // Only the Workerless history table. `jobs` and `failed_jobs` are shared
        // with Laravel itself, so they are left alone.
        Schema::connection(config('queue.connections.workerless.connection'))
            ->dropIfExists(Installer::RUNS_TABLE);

        app(Installer::class)->forget();
    }
};
