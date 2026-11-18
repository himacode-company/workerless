<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Http;

use Closure;
use HimaCode\Workerless\Loopback\Trigger;
use HimaCode\Workerless\Runner\Runner;
use HimaCode\Workerless\Support\ResponseFinisher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: after the response reaches the visitor, run due jobs.
 * If the response cannot be finished early (streamed or file responses on
 * Apache mod_php), nothing runs in this request.
 */
class RunAfterResponse
{
    protected const ATTRIBUTE = 'workerless.run';

    public function __construct(
        protected Runner $runner,
        protected Trigger $trigger,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (! $response instanceof Response
            || isset($_SERVER['LARAVEL_OCTANE'])
            || $request->is(TickController::PATH) // the self-call runs jobs on its own
            || ! $this->runner->shouldRun()) {
            return $response;
        }

        if (ResponseFinisher::prepare($request, $response)) {
            $request->attributes->set(self::ATTRIBUTE, true);
        }

        return $response;
    }

    public function terminate(Request $request, mixed $response): void
    {
        if ($request->attributes->get(self::ATTRIBUTE) !== true) {
            return;
        }

        // Run at the very end of the request, after every other middleware has
        // finished its own terminate(): the clean-up done before the jobs
        // (logged-in user, request, locale) can then affect nothing else.
        app()->terminating(function (): void {
            ResponseFinisher::finish();

            // More work coming soon: hand it to a self-call chain, so it keeps
            // moving even if no visitor comes.
            if ($this->runner->run()) {
                $this->trigger->fire();
            }
        });
    }
}
