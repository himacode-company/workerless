@php
    $nextDue = $summary['next_due'] ?? null;
    $nextText = match (true) {
        ($summary['pending'] ?? 0) > 0 => 'Ready now',
        $nextDue === null || $nextDue === PHP_INT_MAX => 'Nothing scheduled',
        $nextDue <= time() => 'Ready now',
        default => $F::ago($nextDue),
    };

    $max = max(1, max(array_map(fn ($b) => $b['succeeded'] + $b['failed'], $hourly)));
    $total24 = array_sum(array_map(fn ($b) => $b['succeeded'] + $b['failed'], $hourly));

    $attention = array_values(array_filter($checks, fn ($c) => $c['level'] !== 'ok'));
    $statusMap = ['succeeded' => ['ok', 'Succeeded'], 'failed' => ['bad', 'Failed'], 'released' => ['warn', 'Retrying']];
@endphp

<section class="hero hero--{{ $overall[0] }}">
    <div class="hero-main">
        <div class="orb"><span></span></div>
        <div>
            <p class="eyebrow">System status</p>
            <h2 class="hero-title">{{ $overall[1] }}</h2>
            <p class="hero-text">{{ $overall[2] }}</p>
            <div class="hero-actions">
                @if ($monitoring)
                    {{-- Monitoring mode: no hosting check needed. --}}
                @elseif ($diagnostic === null)
                    <form method="POST" action="{{ route('workerless.diagnostics.run') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">@include('workerless::partials.icon', ['name' => 'health']) Check this hosting</button>
                    </form>
                    <span class="muted">Find out in 5 seconds if Workerless fully works here.</span>
                @elseif (! $diagnostic['running'])
                    <a class="btn btn-sm" href="{{ route('workerless.index', ['tab' => 'health']) }}">Hosting check · {{ $F::ago($diagnostic['checked_at']) }}</a>
                @endif
            </div>
        </div>
    </div>
    <dl class="hero-meta">
        <div><dt>Server</dt><dd>{{ $server }}</dd></div>
        <div><dt>Last run</dt><dd title="{{ $F::exact($summary['last_run']) }}">{{ $F::ago($summary['last_run']) }}</dd></div>
        <div><dt>Next job</dt><dd>{{ $nextText }}</dd></div>
        <div><dt>Last self-call</dt><dd title="{{ $F::exact($summary['last_tick']) }}">{{ $F::ago($summary['last_tick']) }}</dd></div>
    </dl>
</section>

<section class="kpis" aria-label="Key numbers">
    <article class="card kpi">
        <div class="kpi-head"><span class="kpi-icon info">@include('workerless::partials.icon', ['name' => 'inbox'])</span>Ready</div>
        <div class="kpi-value">{{ $F::number($summary['pending']) }}</div>
        <div class="kpi-foot">
            @if ($summary['oldest_pending'])
                Oldest waiting since {{ $F::ago((int) $summary['oldest_pending']) }}
            @else
                Waiting for their turn
            @endif
        </div>
    </article>
    <article class="card kpi">
        <div class="kpi-head"><span class="kpi-icon accent">@include('workerless::partials.icon', ['name' => 'clock'])</span>Delayed</div>
        <div class="kpi-value">{{ $F::number($summary['delayed']) }}</div>
        <div class="kpi-foot">{{ (int) ($summary['running'] ?? 0) }} running now</div>
    </article>
    <article class="card kpi">
        <div class="kpi-head"><span class="kpi-icon ok">@include('workerless::partials.icon', ['name' => 'check'])</span>Processed · 24h</div>
        <div class="kpi-value">{{ $F::number($summary['succeeded_24h']) }}</div>
        <div class="kpi-foot">Average {{ $F::duration($summary['avg_ms']) }}</div>
    </article>
    <article class="card kpi">
        <div class="kpi-head"><span class="kpi-icon bad">@include('workerless::partials.icon', ['name' => 'failed'])</span>Failed · 24h</div>
        <div class="kpi-value">{{ $F::number($summary['failed_24h']) }}</div>
        <div class="kpi-foot">{{ (int) ($summary['released_24h'] ?? 0) }} retried · {{ (int) ($summary['failed'] ?? 0) }} stored</div>
    </article>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h2 class="card-title">Throughput</h2>
            <p class="card-sub">{{ number_format($total24) }} jobs finished in the last 24 hours</p>
        </div>
        <div class="end legend">
            <span><i style="background:linear-gradient(180deg,var(--accent-2),var(--accent))"></i>Succeeded</span>
            <span><i style="background:var(--bad)"></i>Failed</span>
        </div>
    </div>
    <div class="card-body">
        <div class="chart" role="img" aria-label="Jobs finished per hour, last 24 hours">
            @foreach ($hourly as $bucket)
                @php($sum = $bucket['succeeded'] + $bucket['failed'])
                <div class="bar" title="{{ $bucket['label'] }} · {{ $bucket['succeeded'] }} succeeded · {{ $bucket['failed'] }} failed">
                    @if ($sum === 0)
                        <div class="seg seg-empty"></div>
                    @else
                        @if ($bucket['failed'] > 0)
                            <div class="seg seg-bad" style="height: {{ max(2, round($bucket['failed'] / $max * 100, 2)) }}%"></div>
                        @endif
                        @if ($bucket['succeeded'] > 0)
                            <div class="seg seg-ok" style="height: {{ max(2, round($bucket['succeeded'] / $max * 100, 2)) }}%"></div>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
        <div class="chart-axis">
            <span>{{ $hourly[0]['label'] }}</span>
            <span>{{ $hourly[6]['label'] }}</span>
            <span>{{ $hourly[12]['label'] }}</span>
            <span>{{ $hourly[18]['label'] }}</span>
            <span>Now</span>
        </div>
    </div>
</section>

<section class="grid-2">
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Recent activity</h2>
                <p class="card-sub">The latest finished jobs</p>
            </div>
            <div class="end"><a class="btn btn-sm" href="{{ route('workerless.index', ['tab' => 'history']) }}">View all</a></div>
        </div>
        @if ($recent === [])
            @include('workerless::partials.empty', ['icon' => 'history', 'title' => 'No activity yet', 'text' => 'Dispatch a job and it will show up here right after it runs.'])
        @else
            <ul class="list">
                @foreach ($recent as $run)
                    @php($s = $statusMap[$run->status] ?? ['muted', ucfirst((string) $run->status)])
                    <li>
                        <span class="dot dot-{{ $s[0] }}" title="{{ $s[1] }}"></span>
                        <div class="grow">
                            <div class="title" title="{{ $run->name }}">{{ $F::shortName((string) $run->name) }}</div>
                            <div class="sub">
                                {{ $s[1] }} · {{ $run->queue }}
                                @if ($run->error) · {{ $run->error }} @endif
                            </div>
                        </div>
                        <div class="end">
                            <div class="num">{{ $F::duration($run->duration_ms === null ? null : (int) $run->duration_ms) }}</div>
                            <div title="{{ $F::exact($run->created_at) }}">{{ $F::ago($run->created_at) }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>

    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Health</h2>
                <p class="card-sub">{{ count($checks) - count($attention) }} of {{ count($checks) }} checks passed</p>
            </div>
            <div class="end"><a class="btn btn-sm" href="{{ route('workerless.index', ['tab' => 'health']) }}">Details</a></div>
        </div>
        @if ($attention === [])
            @include('workerless::partials.empty', ['icon' => 'check', 'tone' => 'ok', 'title' => 'Everything looks great', 'text' => 'All checks passed. Workerless is ready for your jobs.'])
        @else
            <ul class="list checks">
                @foreach (array_slice($attention, 0, 5) as $check)
                    <li>
                        <span class="check-icon check-{{ $check['level'] }}">@include('workerless::partials.icon', ['name' => $check['level'] === 'fail' ? 'x' : 'warn'])</span>
                        <div class="grow">
                            <div class="title">{{ $check['title'] }}</div>
                            <div class="sub">{{ $check['detail'] }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>
</section>
