@foreach ($navItems as $key => $item)
    <a href="{{ route('workerless.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>
        @include('workerless::partials.icon', ['name' => $key])
        <span>{{ $item['label'] }}</span>
        @if (! empty($item['badge']))
            <span class="badge {{ $item['badgeClass'] ?? '' }}">{{ $item['badge'] }}</span>
        @endif
    </a>
@endforeach
