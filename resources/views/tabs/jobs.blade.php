@php
    $statusMap = [
        'pending' => ['info', 'Ready'],
        'delayed' => ['', 'Delayed'],
        'running' => ['accent', 'Running'],
        'stuck' => ['warn', 'Stuck'],
    ];
@endphp

<div class="section-head">
    <div class="chips">
        <span class="pill pill-info">{{ $F::number($summary['pending']) }} ready</span>
        <span class="pill">{{ $F::number($summary['delayed']) }} delayed</span>
        <span class="pill pill-accent">{{ $F::number($summary['running']) }} running</span>
        @if (($summary['stuck'] ?? 0) > 0)
            <span class="pill pill-warn">{{ $F::number($summary['stuck']) }} stuck</span>
        @endif
    </div>
</div>

<section class="card table-card">
    @if ($jobs->isEmpty())
        @include('workerless::partials.empty', ['icon' => 'check', 'tone' => 'ok', 'title' => 'The queue is empty', 'text' => 'Every job has been processed. New jobs appear here until they run.'])
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Status</th>
                    <th>Queue</th>
                    <th class="right">Attempts</th>
                    <th>Runs</th>
                    <th>Queued</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jobs as $job)
                    @php($s = $statusMap[$job['status']])
                    <tr>
                        <td class="cell-main" data-label="Job">@include('workerless::partials.job-name', ['name' => $job['name']])</td>
                        <td data-label="Status"><span class="pill {{ $s[0] ? 'pill-' . $s[0] : '' }}">{{ $s[1] }}</span></td>
                        <td data-label="Queue"><span class="queue-tag">{{ $job['queue'] }}</span></td>
                        <td data-label="Attempts" class="right num">{{ $job['attempts'] }}</td>
                        <td data-label="Runs" title="{{ $F::exact($job['available_at']) }}">
                            {{ in_array($job['status'], ['running', 'stuck'], true) ? 'Now' : ($job['available_at'] <= time() ? 'Next run' : $F::ago($job['available_at'])) }}
                        </td>
                        <td data-label="Queued" class="muted" title="{{ $F::exact($job['created_at']) }}">{{ $F::ago($job['created_at']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('workerless::partials.pager', ['paginator' => $jobs])
    @endif
</section>
