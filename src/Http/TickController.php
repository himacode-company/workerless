<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Http;

use HimaCode\Workerless\Dashboard\Diagnostics;
use HimaCode\Workerless\Loopback\Signer;
use HimaCode\Workerless\Loopback\Trigger;
use HimaCode\Workerless\Runner\Runner;
use HimaCode\Workerless\Support\State;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives the signed self-call: waits until the next job is ready (at most
 * `loopback.max_wait` seconds), runs jobs, then passes the baton to a new self-call.
 *
 * Accepts no input. Anything not perfectly signed gets a plain 404.
 * Only one chain may be alive at a time (chain.lock).
 */
class TickController
{
    public const PATH = '_workerless/tick';

    public function __invoke(
        Request $request,
        Config $config,
        Signer $signer,
        Runner $runner,
        Trigger $trigger,
        State $state,
        Diagnostics $diagnostics,
    ): Response {
        @ini_set('display_errors', '0');

        if (! $config->get('workerless.enabled', true)
            || ! $config->get('workerless.loopback.enabled', true)
            || ! $signer->verify(
                $request->headers->get(Signer::HEADER_TIME),
                $request->headers->get(Signer::HEADER_NONCE),
                $request->headers->get(Signer::HEADER_SIGNATURE),
            )) {
            return new Response('', 404);
        }

        // Shown on the dashboard: proof that self-calls reach this server.
        $state->write('last-tick', time());

        // Diagnostics self-call: report that it arrived, run nothing.
        $probe = $request->headers->get(Trigger::HEADER_PROBE);

        if ($probe !== null) {
            if (preg_match('/^[a-f0-9]{16}$/', (string) $probe) === 1) {
                $diagnostics->markLoopback((string) $probe);
            }

            return new Response('', 204);
        }

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true); // the caller hangs up on purpose
        }

        $lock = $state->lock('chain.lock');

        if ($lock === null) {
            return new Response('', 204); // another chain is alive
        }

        $chain = false;

        try {
            $wait = $runner->secondsUntilReady();
            $maxWait = $runner->maxWait();

            if ($wait <= $runner->horizon()) {
                // Wait for the next job, but never longer than max_wait in one request.
                $sleep = min($wait, max(0, $maxWait));

                if (function_exists('set_time_limit')) {
                    @set_time_limit($sleep + 120);
                }

                if ($sleep > 0) {
                    sleep($sleep);
                }

                // Still too early: hop. A fresh self-call continues the wait,
                // so no single request holds a PHP process for long.
                $chain = $wait > $maxWait ? true : $runner->run();
            }
        } finally {
            $state->unlock($lock);
        }

        // Fired after the lock is released, so the next link can take it.
        if ($chain) {
            $trigger->fire();
        }

        return new Response('', 204);
    }
}
