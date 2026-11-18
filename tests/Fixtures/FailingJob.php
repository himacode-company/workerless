<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

/** Always fails. Two tries, no backoff, so both attempts happen in one run. */
class FailingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 2;

    public static int $attempts = 0;

    public function handle(): void
    {
        static::$attempts++;

        throw new RuntimeException('Workerless test failure');
    }
}
