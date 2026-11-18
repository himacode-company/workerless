<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Send the response to the visitor, then keep working."
 *
 *  - LiteSpeed / PHP-FPM: litespeed_finish_request() / fastcgi_finish_request().
 *  - Apache mod_php / built-in server: Content-Length + Connection: close,
 *    so the browser stops waiting once the body arrives.
 */
class ResponseFinisher
{
    public static function serverCanFinish(): bool
    {
        return function_exists('litespeed_finish_request') || function_exists('fastcgi_finish_request');
    }

    /** Prepares $response so work can continue after it. False = not safe for this response. */
    public static function prepare(Request $request, Response $response): bool
    {
        if (self::serverCanFinish()) {
            return true;
        }

        if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) || $request->isMethod('HEAD')) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status === 204 || $status === 304) {
            return false;
        }

        $content = $response->getContent();

        if ($content === false) {
            return false;
        }

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        @ini_set('zlib.output_compression', '0');

        // Stops Apache (mod_deflate) from re-compressing and dropping Content-Length.
        if (! $response->headers->has('Content-Encoding')) {
            $response->headers->set('Content-Encoding', 'none');
        }

        $response->headers->set('Content-Length', (string) strlen($content));
        $response->headers->set('Connection', 'close');

        return true;
    }

    /** Call after the response was sent. Usually Symfony already did it; calling again is harmless. */
    public static function finish(): void
    {
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
    }
}
