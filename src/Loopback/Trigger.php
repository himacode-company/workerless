<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Loopback;

use HimaCode\Workerless\Http\TickController;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Fires a signed self-call and hangs up without waiting for the answer
 * (proven on Hostinger and Apache mod_php with the queue probe).
 *
 * Always called AFTER the visitor's response was sent, so its cost is invisible.
 * The target URL comes from config only (workerless.loopback.url or app.url),
 * never from the incoming request.
 */
class Trigger
{
    /** How long to keep the connection before hanging up. */
    protected const TIMEOUT_MS = 500;

    /** Marks a diagnostics self-call. */
    public const HEADER_PROBE = 'X-Workerless-Probe';

    public function __construct(
        protected Config $config,
        protected Signer $signer,
    ) {
    }

    public function fire(): void
    {
        if (! $this->config->get('workerless.enabled', true)
            || ! $this->config->get('workerless.loopback.enabled', true)
            || ! function_exists('curl_init')) {
            return;
        }

        $target = $this->target();
        $headers = $this->signer->headers();

        if ($target === null || $headers === null) {
            return;
        }

        // 127.0.0.1 first (fast, skips CDNs and firewalls), then the public address.
        // Each attempt needs fresh headers: a signature is accepted only once.
        if (! $this->send($target, $headers, true)) {
            $headers = $this->signer->headers();

            if ($headers !== null) {
                $this->send($target, $headers, false);
            }
        }
    }

    /**
     * Diagnostics: a signed self-call that only reports it arrived (runs no jobs).
     * Same signature and URL rules as fire().
     */
    public function probe(string $id, bool $local): bool
    {
        if (! function_exists('curl_init')) {
            return false;
        }

        $target = $this->target();
        $headers = $this->signer->headers();

        if ($target === null || $headers === null) {
            return false;
        }

        $headers[] = self::HEADER_PROBE . ': ' . $id;

        return $this->send($target, $headers, $local);
    }

    /**
     * @param  array{url: string, host: string, port: int}  $target
     * @param  array<int, string>  $headers
     */
    protected function send(array $target, array $headers, bool $local): bool
    {
        $ch = curl_init($target['url']);

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_TIMEOUT_MS => self::TIMEOUT_MS,
            CURLOPT_CONNECTTIMEOUT_MS => $local ? 300 : 2000,
            CURLOPT_USERAGENT => 'Workerless',
            CURLOPT_SSL_VERIFYPEER => (bool) $this->config->get('workerless.loopback.verify_ssl', true),
            CURLOPT_SSL_VERIFYHOST => $this->config->get('workerless.loopback.verify_ssl', true) ? 2 : 0,
        ];

        if ($local) {
            $options[CURLOPT_RESOLVE] = [$target['host'] . ':' . $target['port'] . ':127.0.0.1'];
        }

        curl_setopt_array($ch, $options);
        curl_exec($ch);

        $errno = curl_errno($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $sent = (float) curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME) > 0;

        // Answered by our endpoint, or the request was sent and we hung up as planned.
        return ($errno === 0 && $code === 204) || ($errno === CURLE_OPERATION_TIMEDOUT && $sent);
    }

    /** @return array{url: string, host: string, port: int}|null */
    protected function target(): ?array
    {
        $base = (string) ($this->config->get('workerless.loopback.url') ?: $this->config->get('app.url', ''));
        $parts = parse_url($base);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (! in_array($scheme, ['http', 'https'], true) || preg_match('/^[a-z0-9.\-]+$/', $host) !== 1) {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = rtrim($parts['path'] ?? '', '/');
        $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);

        return [
            'url' => $scheme . '://' . $host . ($defaultPort ? '' : ':' . $port) . $path . '/' . TickController::PATH,
            'host' => $host,
            'port' => $port,
        ];
    }
}
