@php
    $waiting = (int) ($summary['pending'] ?? 0) + (int) ($summary['delayed'] ?? 0) + (int) ($summary['running'] ?? 0);
    $delayed = (int) ($summary['delayed'] ?? 0);
    $failedCount = $summary['failed'];
    $title = $overall[0] === 'ok' ? 'Everything works' : $overall[1];
@endphp

<section class="hero hero--{{ $overall[0] }}">
    <div class="hero-main">
        <div class="orb"><span></span></div>
        <div>
            <h2 class="hero-title">{{ $title }}</h2>
            <p class="hero-text">{{ $overall[2] }}</p>
            @if ($diagnostic === null)
                <div class="hero-actions">
                    <form method="POST" action="{{ route('workerless.diagnostics.run') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">@include('workerless::partials.icon', ['name' => 'health']) Check this hosting</button>
                    </form>
                    <span class="muted">5 seconds, changes nothing.</span>
                </div>
            @endif
        </div>
    </div>
</section>

<section class="kpis kpis--3" aria-label="Key numbers">
    <article class="card kpi">
        <div class="kpi-head"><span class="kpi-icon info">@include('workerless::partials.icon', ['name' => 'inbox'])</span>Waiting</div>
        <div class="kpi-value">{{ $F::number($waiting) }}</div>
        <div class="kpi-foot">{{ $delayed > 0 ? $delayed . ' scheduled for later' : 'Ready to run' }}</div>
    </article>
    <article class="card kpi">
        <div class="kpi-head"><span class="kpi-icon ok">@include('workerless::partials.icon', ['name' => 'check'])</span>Done · 24h</div>
        <div class="kpi-value">{{ $F::number($summary['succeeded_24h']) }}</div>
        <div class="kpi-foot">Average {{ $F::duration($summary['avg_ms']) }}</div>
    </article>
    <article class="card kpi">
        <div class="kpi-head"><span class="kpi-icon bad">@include('workerless::partials.icon', ['name' => 'failed'])</span>Failed</div>
        <div class="kpi-value">{{ $F::number($failedCount) }}</div>
        <div class="kpi-foot">{{ ($failedCount ?? 0) > 0 ? 'Waiting for a retry' : 'All good' }}</div>
    </article>
</section>

<section class="card">
    <div class="card-head">
        <h2 class="card-title">Failed jobs</h2>
        @if ($failed !== null && ! $failed->isEmpty())
            <div class="end">
                <form method="POST" action="{{ route('workerless.failed.retry-all') }}" data-confirm="Every failed job goes back to the queue and runs again." data-confirm-title="Retry all failed jobs?" data-confirm-button="Retry all" data-confirm-tone="accent">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">@include('workerless::partials.icon', ['name' => 'retry']) Retry all</button>
                </form>
            </div>
        @endif
    </div>

    @if ($failed === null)
        @include('workerless::partials.empty', ['icon' => 'failed', 'title' => 'Stored elsewhere', 'text' => 'Failed jobs are not kept in the database on this app.'])
    @elseif ($failed->isEmpty())
        @include('workerless::partials.empty', ['icon' => 'check', 'tone' => 'ok', 'title' => 'No failed jobs', 'text' => 'Nothing needs your attention.'])
    @else
        <ul class="list failed-list">
            @foreach ($failed as $job)
                <li>
                    <span class="dot dot-bad"></span>
                    <div class="grow">
                        <div class="title" title="{{ $job['name'] }}">{{ $F::shortName($job['name']) }}</div>
                        <div class="sub" title="{{ $job['error'] }}">{{ $job['error'] }}</div>
                    </div>
                    <span class="end" title="{{ $F::exact($job['failed_at']) }}">{{ $F::ago($job['failed_at']) }}</span>
                    <div class="actions">
                        <form method="POST" action="{{ route('workerless.failed.retry', ['id' => $job['key']]) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm">@include('workerless::partials.icon', ['name' => 'retry']) Retry</button>
                        </form>
                        <form method="POST" action="{{ route('workerless.failed.delete', ['id' => $job['key']]) }}" data-confirm="{{ $F::shortName($job['name']) }} will be removed for good. This cannot be undone." data-confirm-title="Delete this failed job?" data-confirm-button="Delete" data-confirm-tone="danger">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-icon btn-ghost-bad" title="Delete" aria-label="Delete">@include('workerless::partials.icon', ['name' => 'trash'])</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        @include('workerless::partials.pager', ['paginator' => $failed])
    @endif
</section>

<p class="simple-foot">Need charts, history or server details? Switch to <strong>Advanced</strong> at the top.</p>
