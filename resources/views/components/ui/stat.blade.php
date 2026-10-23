@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => 'brand', 'href' => null])
@php
    $iconTones = ['brand' => 'bg-brand-50 text-brand-700', 'gold' => 'bg-gold-50 text-gold-600', 'danger' => 'bg-danger-soft text-danger', 'warning' => 'bg-warning-soft text-warning', 'info' => 'bg-info-soft text-info'];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['card card-pad group flex flex-col gap-4', 'transition hover:border-line-strong hover:shadow-[var(--shadow-raised)]' => $href]) }}>
    <div class="flex items-center justify-between">
        <span class="eyebrow">{{ $label }}</span>
        @if ($icon)
            <span class="grid size-9 place-items-center rounded-xl {{ $iconTones[$tone] ?? $iconTones['brand'] }}"><x-icon :name="$icon" class="size-[1.125rem]" /></span>
        @endif
    </div>
    <div>
        <div class="text-[1.75rem] font-bold leading-none tracking-tight tnum">{{ $value }}</div>
        @if ($hint)<p class="mt-2 text-[0.8125rem] text-muted">{{ $hint }}</p>@endif
    </div>
</{{ $tag }}>
