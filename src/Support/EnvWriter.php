<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Support;

use Throwable;

/**
 * Prepares the app's .env and .env.example right after `composer require`
 * (during `php artisan package:discover`), so the developer immediately sees
 * what to fill in.
 *
 * It only ADDS lines: no existing value is ever changed or removed.
 *  - Adds an empty WORKERLESS_USER_IDS= with a comment.
 *  - Adds a comment above QUEUE_CONNECTION saying to set it to "workerless".
 */
class EnvWriter
{
    /** @var array<string, string> key => comment shown above it */
    public const KEYS = [
        'WORKERLESS_USER_IDS' => 'Workerless: ids from your users table allowed to open /workerless (example: 1,7)',
    ];

    public const QUEUE_HINT = '# Workerless: set QUEUE_CONNECTION=workerless to run jobs without cron or queue:work';

    public function __construct(
        protected string $basePath,
    ) {
    }

    public function addMissingKeys(): void
    {
        foreach (['.env', '.env.example'] as $file) {
            $this->update($this->basePath . DIRECTORY_SEPARATOR . $file);
        }
    }

    protected function update(string $path): void
    {
        try {
            if (! is_file($path) || ! is_writable($path)) {
                return;
            }

            $original = (string) file_get_contents($path);
            $eol = str_contains($original, "\r\n") ? "\r\n" : "\n";

            $content = $this->addQueueHint($original, $eol);
            $content = $this->addKeys($content, $eol);

            if ($content !== $original) {
                file_put_contents($path, $content, LOCK_EX);
            }
        } catch (Throwable) {
            // Never break composer because of this; the README explains it too.
        }
    }

    /** Comment above QUEUE_CONNECTION, unless it is already "workerless" or hinted. */
    protected function addQueueHint(string $content, string $eol): string
    {
        if (str_contains($content, self::QUEUE_HINT)) {
            return $content;
        }

        if (preg_match('/^\s*QUEUE_CONNECTION\s*=\s*"?workerless"?\s*$/m', $content) === 1) {
            return $content;
        }

        if (preg_match('/^\s*QUEUE_CONNECTION\s*=/m', $content) === 1) {
            return (string) preg_replace('/^(\s*QUEUE_CONNECTION\s*=)/m', self::QUEUE_HINT . $eol . '$1', $content, 1);
        }

        // No QUEUE_CONNECTION line at all: add the hint at the end.
        return $this->append($content, self::QUEUE_HINT . $eol, $eol);
    }

    protected function addKeys(string $content, string $eol): string
    {
        $block = '';

        foreach (self::KEYS as $key => $comment) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/m', $content) !== 1) {
                $block .= '# ' . $comment . $eol . $key . '=' . $eol;
            }
        }

        return $block === '' ? $content : $this->append($content, $block, $eol);
    }

    /** Appends a block separated from the rest by one empty line. */
    protected function append(string $content, string $block, string $eol): string
    {
        if ($content === '') {
            return $block;
        }

        return rtrim($content, "\r\n") . $eol . $eol . $block;
    }
}
