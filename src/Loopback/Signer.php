<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Loopback;

use HimaCode\Workerless\Support\State;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Signs and verifies self-calls.
 *
 * Signature = HMAC-SHA256 over "tick\n{time}\n{nonce}" with a key derived from APP_KEY.
 * A signature is valid for `loopback.signature_ttl` seconds and only ONCE (nonce).
 * Without APP_KEY nothing is signed and nothing is accepted.
 */
class Signer
{
    public const HEADER_TIME = 'X-Workerless-Time';
    public const HEADER_NONCE = 'X-Workerless-Nonce';
    public const HEADER_SIGNATURE = 'X-Workerless-Signature';

    public function __construct(
        protected Config $config,
        protected State $state,
    ) {
    }

    /** @return array<int, string>|null  Request headers, or null when signing is impossible. */
    public function headers(): ?array
    {
        $key = $this->key();

        if ($key === null) {
            return null;
        }

        $time = (string) time();
        $nonce = bin2hex(random_bytes(16));

        return [
            self::HEADER_TIME . ': ' . $time,
            self::HEADER_NONCE . ': ' . $nonce,
            self::HEADER_SIGNATURE . ': ' . $this->sign($key, $time, $nonce),
        ];
    }

    public function verify(mixed $time, mixed $nonce, mixed $signature): bool
    {
        $key = $this->key();

        if ($key === null || ! is_string($time) || ! is_string($nonce) || ! is_string($signature)) {
            return false;
        }

        if (! ctype_digit($time)
            || preg_match('/^[a-f0-9]{32}$/', $nonce) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
            return false;
        }

        $ttl = $this->ttl();

        if (abs(time() - (int) $time) > $ttl) {
            return false;
        }

        if (! hash_equals($this->sign($key, $time, $nonce), $signature)) {
            return false;
        }

        // Valid signature: accept it only the first time it is seen.
        return $this->state->claimNonce($nonce, $ttl * 2);
    }

    protected function sign(string $key, string $time, string $nonce): string
    {
        return hash_hmac('sha256', "tick\n{$time}\n{$nonce}", $key);
    }

    protected function key(): ?string
    {
        $appKey = (string) $this->config->get('app.key', '');

        return $appKey === '' ? null : hash_hmac('sha256', 'workerless-loopback-v1', $appKey, true);
    }

    protected function ttl(): int
    {
        return max(10, (int) $this->config->get('workerless.loopback.signature_ttl', 60));
    }
}
