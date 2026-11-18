<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Support;

/**
 * Tiny state files in storage/framework/workerless, so deciding "is there work?"
 * costs a file read, not a database query, on every request.
 *
 *  - next-due:   unix time of the earliest job that may be ready
 *  - last-run:   unix time the runner last started
 *  - last-check: unix time the runner last looked at the database
 *  - runner.lock: flock() so only one request runs jobs at a time
 *                 (released by the OS even if PHP dies)
 */
class State
{
    public function __construct(
        protected string $directory,
    ) {
    }

    public function read(string $name): ?int
    {
        $value = @file_get_contents($this->path($name));

        if ($value === false) {
            return null;
        }

        $value = trim($value);

        return ctype_digit($value) ? (int) $value : null;
    }

    public function write(string $name, int $value): void
    {
        if ($this->ensureDirectory()) {
            @file_put_contents($this->path($name), (string) $value, LOCK_EX);
        }
    }

    /** @return array<string, mixed>|null */
    public function readJson(string $name): ?array
    {
        $raw = @file_get_contents($this->path($name . '.json'));
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : null;
    }

    /** @param  array<string, mixed>  $data */
    public function writeJson(string $name, array $data): void
    {
        if ($this->ensureDirectory()) {
            @file_put_contents($this->path($name . '.json'), (string) json_encode($data), LOCK_EX);
        }
    }

    public function forget(string $name): void
    {
        @unlink($this->path($name));
    }

    public function has(string $name): bool
    {
        return is_file($this->path($name));
    }

    /** Remember that a job may be ready at $time (keeps the earliest one). */
    public function noteDueAt(int $time): void
    {
        $current = $this->read('next-due');

        if ($current === null || $time < $current) {
            $this->write('next-due', $time);
        }
    }

    /**
     * True when a job may be ready, or when the database was not checked for
     * $safetySeconds (catches jobs added by other means, e.g. queue:retry).
     */
    public function isDue(int $now, int $safetySeconds): bool
    {
        $next = $this->read('next-due');

        if ($next !== null && $next <= $now) {
            return true;
        }

        $checked = $this->read('last-check');

        return $checked === null || $now - $checked >= $safetySeconds;
    }

    /**
     * Claims a one-time nonce. False if it was already used.
     * The file is created with mode "x", which fails atomically if it exists.
     */
    public function claimNonce(string $nonce, int $ttl): bool
    {
        $dir = $this->path('nonces');

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return false;
        }

        $handle = @fopen($dir . DIRECTORY_SEPARATOR . $nonce, 'x');

        if ($handle === false) {
            return false;
        }

        fclose($handle);

        // Forget nonces older than their lifetime.
        foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_string($file) && @filemtime($file) < time() - $ttl) {
                @unlink($file);
            }
        }

        return true;
    }

    /**
     * @param  string  $name  runner.lock (runs jobs) or chain.lock (self-call chain)
     * @return resource|null  An exclusive lock, or null if another request holds it.
     */
    public function lock(string $name = 'runner.lock'): mixed
    {
        if (! $this->ensureDirectory()) {
            return null;
        }

        $handle = @fopen($this->path($name), 'c');

        if ($handle === false) {
            return null;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /** @param  resource  $handle */
    public function unlock(mixed $handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    protected function path(string $name): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $name;
    }

    protected function ensureDirectory(): bool
    {
        return is_dir($this->directory)
            || @mkdir($this->directory, 0755, true)
            || is_dir($this->directory);
    }
}
