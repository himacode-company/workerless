@php
    $statusMap = ['succeeded' => ['ok', 'Succeeded'], 'failed' => ['bad', 'Failed'], 'released' => ['warn', 'Retrying']];
    $filters = [null => 'All', 'succeeded' => 'Succeeded', 'failed' => 'Failed', 'released' => 'Retried'];
    $keepsFailed = ' Jobs in the Failed tab stay and can still be retried.';
    $clearButtons = [
        'succeeded' => ['Clear succeeded', 'Delete succeeded history?', 'Every succeeded entry is removed from the history.'],
        'failed' => ['Clear failed', 'Delete failed history?', 'Every failed entry is removed from the history.' . $keepsFailed],
        'all' => ['Clear all', 'Delete the whole history?', 'Every entry is removed from the history.' . $keepsFailed],
    ];
@endphp

<div class="section-head">
    <nav class="seg" aria-label="Filter by status">
        @foreach ($filters as $value => $label)
            <a href="{{ route('workerless.index', array_filter(['tab' => 'history', 'status' => $value ?: null])) }}" @if (($status ?? null) === ($value ?: null)) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if (! $runs->isEmpty() || ($status ?? null) !== null)
        <div class="end">
            @foreach ($clearButtons as $scope => $button)
                @php([$label, $confirmTitle, $confirmText] = $button)
                <form method="POST" action="{{ route('workerless.history.clear') }}"
                      data-confirm="{{ $confirmText }}" data-confirm-title="{{ $confirmTitle }}" data-confirm-button="Delete" data-confirm-tone="danger">
                    @csrf
                    <input type="hidden" name="scope" value="{{ $scope }}">
                    <button type="submit" class="btn btn-sm {{ $scope === 'all' ? 'btn-ghost-bad' : '' }}">@include('workerless::partials.icon', ['name' => 'trash']) {{ $label }}</button>
                </form>
            @endforeach
        </div>
    @endif
</div>

<section class="card table-card">
    @if ($runs->isEmpty())
        @include('workerless::partials.empty', ['icon' => 'history', 'title' => 'Nothing here yet', 'text' => 'Finished jobs are recorded here and kept for ' . (int) config('workerless.history.keep_days', 7) . ' days.'])
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Status</th>
                    <th>Queue</th>
                    <th class="right">Attempt</th>
                    <th class="right">Duration</th>
                    <th>Error</th>
                    <th>Finished</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    @php($s = $statusMap[$run->status] ?? ['', ucfirst((string) $run->status)])
                    <tr>
                        <td class="cell-main" data-label="Job">@include('workerless::partials.job-name', ['name' => (string) $run->name])</td>
                        <td data-label="Status"><span class="pill {{ $s[0] ? 'pill-' . $s[0] : '' }}">{{ $s[1] }}</span></td>
                        <td data-label="Queue"><span class="queue-tag">{{ $run->queue }}</span></td>
                        <td data-label="Attempt" class="right num">{{ $run->attempts }}</td>
                        <td data-label="Duration" class="right num">{{ $F::duration($run->duration_ms === null ? null : (int) $run->duration_ms) }}</td>
                        <td data-label="Error">
                            @if ($run->error)
                                <span class="error-text" title="{{ $run->error }}">{{ $run->error }}</span>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td data-label="Finished" class="muted" title="{{ $F::exact($run->created_at) }}">{{ $F::ago($run->created_at) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('workerless::partials.pager', ['paginator' => $runs])
    @endif
</section>
