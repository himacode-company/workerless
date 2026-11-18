<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Dashboard access. Anyone not allowed gets a plain 404, so nobody can even
 * tell the dashboard exists.
 *
 * Allowed when one of these is true:
 *  1. The logged-in user's id is listed in WORKERLESS_USER_IDS (e.g. "1,7").
 *  2. The app defines the `viewWorkerless` gate and it allows the user.
 *  3. The app runs in the `local` environment AND the request comes from this machine.
 */
class Authorize
{
    public function __construct(
        protected Application $app,
        protected Config $config,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->allowedById($request) || Gate::allows('viewWorkerless') || $this->localMachine($request)) {
            return $next($request);
        }

        throw new NotFoundHttpException();
    }

    /**
     * Open without login only in `local` AND for a request from this same machine.
     * Protects a site uploaded with APP_ENV=local by mistake.
     * REMOTE_ADDR is used directly: proxy headers (X-Forwarded-For) can be forged.
     */
    protected function localMachine(Request $request): bool
    {
        if (! $this->app->environment('local')) {
            return false;
        }

        return in_array((string) $request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true);
    }

    protected function allowedById(Request $request): bool
    {
        $user = $request->user();

        if ($user === null || ! method_exists($user, 'getAuthIdentifier')) {
            return false;
        }

        $allowed = $this->config->get('workerless.dashboard.user_ids', []);

        if (is_string($allowed)) {
            $allowed = explode(',', $allowed);
        }

        $allowed = array_filter(array_map(fn ($id) => trim((string) $id), (array) $allowed), fn ($id) => $id !== '');

        // Compared as strings: works for numeric ids and UUIDs alike.
        return in_array((string) $user->getAuthIdentifier(), $allowed, true);
    }
}
