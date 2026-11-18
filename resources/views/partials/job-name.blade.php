@php($namespace = str_contains($name, '\\') ? substr($name, 0, (int) strrpos($name, '\\')) : null)
<span class="job-name" title="{{ $name }}">{{ $F::shortName($name) }}</span>
@if ($namespace)
    <span class="job-ns" title="{{ $name }}">{{ $namespace }}</span>
@endif
