@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">
        <a class="btn btn-sm" href="{{ $paginator->previousPageUrl() ?? '#' }}" @if ($paginator->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>
            @include('workerless::partials.icon', ['name' => 'arrow-left']) Previous
        </a>
        <span class="muted num">Page {{ $paginator->currentPage() }}</span>
        <a class="btn btn-sm" href="{{ $paginator->nextPageUrl() ?? '#' }}" @if (! $paginator->hasMorePages()) aria-disabled="true" tabindex="-1" @endif>
            Next @include('workerless::partials.icon', ['name' => 'arrow-right'])
        </a>
    </nav>
@endif
