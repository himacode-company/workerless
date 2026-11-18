<div class="empty">
    <div class="empty-icon {{ $tone ?? '' }}">@include('workerless::partials.icon', ['name' => $icon ?? 'inbox'])</div>
    <h3>{{ $title }}</h3>
    <p>{{ $text }}</p>
</div>
