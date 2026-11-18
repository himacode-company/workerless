<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Remembers which user it saw, to prove the visitor's login never leaks into jobs. */
class WhoAmIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public static string $seen = 'not run';

    public function handle(): void
    {
        $id = auth()->id();

        static::$seen = $id === null ? 'guest' : 'user ' . $id;
    }
}
