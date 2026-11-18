<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Queue;

use Illuminate\Queue\DatabaseQueue;

/**
 * The Workerless queue.
 *
 * Built on Laravel's own DatabaseQueue, so jobs live in the standard `jobs` table
 * and everything Laravel already supports keeps working: delays, tries, backoff,
 * `failed_jobs`, `queue:retry`, `queue:failed`, and even `queue:work` if a real
 * worker is ever available.
 *
 * Phase 1: storage only. Running jobs after the response is added in phase 2.
 */
class WorkerlessQueue extends DatabaseQueue
{
}
