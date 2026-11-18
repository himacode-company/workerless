@if ($failed === null)
    <section class="card">
        @include('workerless::partials.empty', [
            'icon' => 'failed',
            'title' => 'Failed jobs are stored elsewhere',
            'text' => 'queue.failed.driver is "' . config('queue.failed.driver') . '". The dashboard can list failed jobs only from the database.',
        ])
    </section>
@else
    <div class="section-head">
        <div class="chips">
            <span class="pill {{ ($summary['failed'] ?? 0) > 0 ? 'pill-bad' : 'pill-ok' }}">{{ $F::number($summary['failed']) }} failed</span>
        </div>
        @if (! $failed->isEmpty())
            <div class="end">
                <form method="POST" action="{{ route('workerless.failed.retry-all') }}" data-confirm="Every failed job goes back to the queue and runs again." data-confirm-title="Retry all failed jobs?" data-confirm-button="Retry all" data-confirm-tone="accent">
                    @csrf
                    <button type="submit" class="btn btn-primary">@include('workerless::partials.icon', ['name' => 'retry']) Retry all</button>
                </form>
            </div>
        @endif
    </div>

    <section class="card table-card">
        @if ($failed->isEmpty())
            @include('workerless::partials.empty', ['icon' => 'check', 'tone' => 'ok', 'title' => 'No failed jobs', 'text' => 'Jobs that run out of attempts will appear here, ready to retry.'])
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th>Error</th>
                        <th>Queue</th>
                        <th>Failed</th>
                        <th class="right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($failed as $job)
                        <tr>
                            <td class="cell-main" data-label="Job">@include('workerless::partials.job-name', ['name' => $job['name']])</td>
                            <td data-label="Error"><span class="error-text" title="{{ $job['error'] }}">{{ $job['error'] }}</span></td>
                            <td data-label="Queue"><span class="queue-tag">{{ $job['queue'] }}</span></td>
                            <td data-label="Failed" class="muted" title="{{ $F::exact($job['failed_at']) }}">{{ $F::ago($job['failed_at']) }}</td>
                            <td data-label="Actions">
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
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('workerless::partials.pager', ['paginator' => $failed])
        @endif
    </section>
@endif
