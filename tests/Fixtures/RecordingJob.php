<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Succeeds and remembers that it ran. */
class RecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @var array<int, string> */
    public static array $handled = [];

    public function __construct(public string $label = 'ok')
    {
    }

    public function handle(): void
    {
        static::$handled[] = $this->label;
    }
}
