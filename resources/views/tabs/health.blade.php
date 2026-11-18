@php
    $passed = count(array_filter($checks, fn ($c) => $c['level'] === 'ok'));
    $order = ['fail' => 0, 'warn' => 1, 'ok' => 2];
    $sorted = $checks;
    usort($sorted, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
@endphp

@php
    $verdicts = [
        'ok' => ['Compatible', 'Workerless fully works on this hosting.', 'check'],
        'warn' => ['Works with limits', null, 'warn'],
        'fail' => ['Not compatible', null, 'x'],
        'running' => ['Checking this hosting', 'Running real tests on this server. About 5 seconds.', null],
    ];
    $v = $diagnostic === null ? null : $verdicts[$diagnostic['verdict']];
@endphp

@if (! $monitoring)
<section class="card compat compat--{{ $diagnostic['verdict'] ?? 'none' }}">
    <div class="card-head">
        <div>
            <h2 class="card-title">Hosting compatibility</h2>
            <p class="card-sub">
                @if ($diagnostic === null)
                    Not checked yet
                @else
                    Checked {{ $F::ago($diagnostic['checked_at']) }}
                @endif
            </p>
        </div>
        <div class="end">
            <form method="POST" action="{{ route('workerless.diagnostics.run') }}">
                @csrf
                <button type="submit" class="btn {{ $diagnostic === null ? 'btn-primary' : '' }}" @if ($diagnostic['running'] ?? false) disabled @endif>
                    @include('workerless::partials.icon', ['name' => 'retry'])
                    {{ $diagnostic === null ? 'Run diagnostics' : 'Run again' }}
                </button>
            </form>
        </div>
    </div>

    @if ($diagnostic === null)
        <div class="verdict">
            <span class="verdict-icon">@include('workerless::partials.icon', ['name' => 'health'])</span>
            <div>
                <h3 class="verdict-title">Is this hosting ready for Workerless?</h3>
                <p class="verdict-text">Run a real test on this server: does the page reach visitors before jobs run, does work keep running after the page, and can the site call itself. It takes about 5 seconds and changes nothing.</p>
            </div>
        </div>
    @else
        <div class="verdict">
            <span class="verdict-icon">
                @if ($v[2])
                    @include('workerless::partials.icon', ['name' => $v[2]])
                @else
                    <span class="mini-spin"></span>
                @endif
            </span>
            <div>
                <h3 class="verdict-title">{{ $v[0] }}</h3>
                <p class="verdict-text">{{ $v[1] ?? $diagnostic['reason'] }}</p>
            </div>
        </div>
        <ul class="list checks">
            @foreach ($diagnostic['checks'] as $check)
                <li>
                    <span class="check-icon check-{{ $check['level'] === 'pending' ? 'pending' : $check['level'] }}">
                        @if ($check['level'] === 'pending')
                            <span class="mini-spin"></span>
                        @else
                            @include('workerless::partials.icon', ['name' => $check['level'] === 'ok' ? 'check' : ($check['level'] === 'warn' ? 'warn' : 'x')])
                        @endif
                    </span>
                    <div class="grow">
                        <div class="title">{{ $check['title'] }}</div>
                        <div class="sub">{{ $check['detail'] }}</div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endif

<div class="section-head">
    <div class="chips">
        <span class="pill pill-ok">{{ $passed }} passed</span>
        @if (count($checks) - $passed > 0)
            <span class="pill {{ $health === 'fail' ? 'pill-bad' : 'pill-warn' }}">{{ count($checks) - $passed }} to review</span>
        @endif
    </div>
</div>

<section class="grid-2">
    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Checks</h2>
                <p class="card-sub">Problems first, with how to fix them</p>
            </div>
        </div>
        <ul class="list checks">
            @foreach ($sorted as $check)
                <li>
                    <span class="check-icon check-{{ $check['level'] }}">
                        @include('workerless::partials.icon', ['name' => $check['level'] === 'ok' ? 'check' : ($check['level'] === 'warn' ? 'warn' : 'x')])
                    </span>
                    <div class="grow">
                        <div class="title">{{ $check['title'] }}</div>
                        <div class="sub">{{ $check['detail'] }}</div>
                    </div>
                </li>
            @endforeach
        </ul>
    </article>

    <article class="card">
        <div class="card-head">
            <div>
                <h2 class="card-title">Environment</h2>
                <p class="card-sub">What this server gives Workerless</p>
            </div>
        </div>
        <dl class="env">
            @foreach ($environment as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    </article>
</section>
