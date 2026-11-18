@php
    $F = \HimaCode\Workerless\Dashboard\Format::class;

    $issues = count(array_filter($checks, fn ($c) => $c['level'] !== 'ok'));
    $waiting = (int) ($summary['pending'] ?? 0) + (int) ($summary['delayed'] ?? 0) + (int) ($summary['running'] ?? 0);

    $navItems = [
        'overview' => ['label' => 'Overview'],
        'jobs' => ['label' => 'Jobs', 'badge' => $waiting ?: null],
        'failed' => ['label' => 'Failed', 'badge' => ($summary['failed'] ?? 0) ?: null, 'badgeClass' => 'badge-bad'],
        'history' => ['label' => 'History'],
        'health' => ['label' => 'Health', 'badge' => $issues ?: null, 'badgeClass' => $health === 'fail' ? 'badge-bad' : 'badge-warn'],
    ];

    $titles = [
        'simple' => ['Workerless', 'Your queue, in one look.'],
        'overview' => ['Overview', 'Everything about your queue at a glance.'],
        'jobs' => ['Jobs', 'Jobs waiting, delayed or running right now.'],
        'failed' => ['Failed jobs', 'Retry or remove jobs that ran out of attempts.'],
        'history' => ['History', 'Every finished job from the last ' . (int) config('workerless.history.keep_days', 7) . ' days.'],
        'health' => ['Health', 'Checks that keep Workerless running smoothly.'],
    ];

    // The first problem, in plain words: the reason, nothing else.
    $firstIssue = function (string $level) use ($checks): ?string {
        foreach ($checks as $check) {
            if ($check['level'] === $level) {
                return $check['title'] . '. ' . $check['detail'];
            }
        }

        return null;
    };

    $verdict = $diagnostic['verdict'] ?? null;
    $failed24 = (int) ($summary['failed_24h'] ?? 0);

    $overall = match (true) {
        $verdict === 'fail' => ['fail', 'Not compatible with this hosting', $diagnostic['reason']],
        $health === 'fail' => ['fail', 'Action required', $firstIssue('fail')],
        $verdict === 'running' => ['live', 'Checking this hosting', 'Diagnostics are running. This takes about 5 seconds.'],
        $verdict === 'warn' => ['warn', 'Works with limits', $diagnostic['reason']],
        $health === 'warn' => ['warn', 'Needs attention', $firstIssue('warn')],
        $failed24 > 0 => ['warn', 'Needs attention', $failed24 . ' ' . ($failed24 === 1 ? 'job' : 'jobs') . ' failed in the last 24 hours. See the Failed tab.'],
        ($summary['running'] ?? 0) > 0 => ['live', 'Processing', 'Jobs are running right now, right after a response.'],
        default => ['ok', 'All systems operational', 'Jobs run right after each response. No cron, no worker.'],
    };

    $flash = session('workerless.flash');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $titles[$tab][0] }} · Workerless</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ rawurlencode(trim(view('workerless::partials.logo', ['size' => 64, 'uid' => 'f'])->render())) }}">
    <meta name="theme-color" content="#120B09">
    @if ($verdict === 'running')
        <meta http-equiv="refresh" content="2;url={{ route('workerless.index', ['tab' => $tab]) }}">
    @endif
    <script>
        (function () {
            try {
                var t = localStorage.getItem('workerless-theme');
                if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    @include('workerless::partials.styles')
</head>
<body>
<div class="overlay" aria-hidden="true"><div class="spinner"></div></div>
@php($repo = \HimaCode\Workerless\WorkerlessServiceProvider::REPOSITORY)
<div class="app {{ $mode === 'simple' ? 'app--simple' : '' }}">
    @if ($mode === 'advanced')
    <aside class="sidebar">
        <a class="brand" href="{{ route('workerless.index') }}">
            <span class="brand-mark">@include('workerless::partials.logo', ['size' => 38, 'uid' => 's'])</span>
            <span>
                <span class="brand-name">worker<span class="brand-less">less</span></span>
                <span class="brand-sub" style="display:block">Queue without workers</span>
            </span>
        </a>

        <nav class="nav" aria-label="Dashboard">
            <span class="nav-label">Monitor</span>
            @include('workerless::partials.nav')
        </nav>

        <nav class="nav" aria-label="Help">
            <span class="nav-label">Help</span>
            <a href="{{ $repo }}#readme" target="_blank" rel="noopener noreferrer">
                @include('workerless::partials.icon', ['name' => 'book'])
                <span>Documentation</span>
                <span class="nav-ext">@include('workerless::partials.icon', ['name' => 'external'])</span>
            </a>
            <a href="{{ $repo }}/issues" target="_blank" rel="noopener noreferrer">
                @include('workerless::partials.icon', ['name' => 'github'])
                <span>Report an issue</span>
                <span class="nav-ext">@include('workerless::partials.icon', ['name' => 'external'])</span>
            </a>
        </nav>

        <div class="sidebar-foot">
            <div class="row"><span>Environment</span><strong>{{ app()->environment() }}</strong></div>
            <div class="row"><span>Server</span><strong>{{ $server }}</strong></div>
            <div class="row"><span>Version</span><strong>{{ \HimaCode\Workerless\WorkerlessServiceProvider::VERSION }}</strong></div>
        </div>
    </aside>
    @endif

    <div class="main">
        <header class="topbar">
            <a class="brand" href="{{ route('workerless.index') }}">
                <span class="brand-mark">@include('workerless::partials.logo', ['size' => 34, 'uid' => 't'])</span>
                <span class="brand-name">worker<span class="brand-less">less</span></span>
            </a>
            @if ($mode === 'advanced')
                <div class="titles">
                    <h1 class="page-title">{{ $titles[$tab][0] }}</h1>
                    <p class="page-sub">{{ $titles[$tab][1] }}</p>
                </div>
            @endif
            <div class="topbar-actions">
                <form method="POST" action="{{ route('workerless.mode') }}" class="seg mode-switch" aria-label="Dashboard mode">
                    @csrf
                    <button type="submit" name="mode" value="simple" @if ($mode === 'simple') aria-current="page" @endif>Simple</button>
                    <button type="submit" name="mode" value="advanced" @if ($mode === 'advanced') aria-current="page" @endif>Advanced</button>
                </form>
                @if ($mode === 'simple')
                    <a class="btn btn-icon" href="{{ $repo }}#readme" target="_blank" rel="noopener noreferrer" title="Documentation on GitHub" aria-label="Documentation on GitHub">
                        @include('workerless::partials.icon', ['name' => 'book'])
                    </a>
                @endif
                <button type="button" class="btn" data-live-toggle aria-pressed="false" title="Refresh every 10 seconds">
                    <span class="live-dot"></span><span class="label">Live</span>
                </button>
                <button type="button" class="btn btn-icon" data-theme-toggle title="Switch theme" aria-label="Switch theme">
                    @include('workerless::partials.icon', ['name' => 'sun'])
                </button>
            </div>
        </header>

        @if ($mode === 'advanced')
            <nav class="tabs" aria-label="Dashboard">
                @include('workerless::partials.nav')
            </nav>
        @endif

        <main class="content">
            @if ($mode === 'advanced')
                <div class="mobile-title">
                    <h1 class="page-title">{{ $titles[$tab][0] }}</h1>
                    <p class="page-sub">{{ $titles[$tab][1] }}</p>
                </div>
            @endif

            @if (is_array($flash) && isset($flash['message']))
                @php($flashLevel = in_array($flash['level'] ?? 'ok', ['ok', 'warn', 'fail'], true) ? $flash['level'] : 'ok')
                <div class="flash flash-{{ $flashLevel }}" role="status">
                    @include('workerless::partials.icon', ['name' => $flashLevel === 'ok' ? 'check' : ($flashLevel === 'warn' ? 'warn' : 'x')])
                    <span>{{ $flash['message'] }}</span>
                </div>
            @endif

            @if ($error)
                <div class="flash flash-fail" role="alert">
                    @include('workerless::partials.icon', ['name' => 'x'])
                    <span>{{ $error }}</span>
                </div>
            @else
                @include('workerless::tabs.' . $tab)
            @endif
        </main>
    </div>
</div>
<dialog class="modal" id="wl-confirm" aria-labelledby="wl-confirm-title" aria-describedby="wl-confirm-text" data-tone="danger">
    <form method="dialog" class="modal-box">
        <div class="modal-icon">
            <span class="tone-danger">@include('workerless::partials.icon', ['name' => 'trash'])</span>
            <span class="tone-accent">@include('workerless::partials.icon', ['name' => 'retry'])</span>
        </div>
        <h2 class="modal-title" id="wl-confirm-title">Are you sure?</h2>
        <p class="modal-text" id="wl-confirm-text"></p>
        <div class="modal-actions">
            <button type="submit" value="cancel" class="btn" autofocus>Cancel</button>
            <button type="submit" value="ok" class="btn modal-ok" id="wl-confirm-ok">Confirm</button>
        </div>
    </form>
</dialog>

@include('workerless::partials.scripts')
</body>
</html>
