@props(['items' => []])
<dl {{ $attributes->class('divide-y divide-line') }}>
    @foreach ($items as $term => $value)
        @continue($value === null || $value === '')
        <div class="flex items-start justify-between gap-6 py-3 text-sm first:pt-0 last:pb-0">
            <dt class="shrink-0 text-muted">{{ $term }}</dt>
            <dd class="min-w-0 text-right font-semibold text-ink break-words">{{ $value }}</dd>
        </div>
    @endforeach
    {{ $slot }}
</dl>
