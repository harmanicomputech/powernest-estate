@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'type' => 'submit',
    'busy' => false,
])
@php
    $base = 'inline-flex items-center justify-center gap-2 font-semibold whitespace-nowrap select-none transition-[background-color,box-shadow,color,transform] duration-150 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-55 focus-visible:outline-none focus-visible:shadow-[var(--shadow-focus)]';
    $sizes = [
        'sm' => 'h-9 px-3.5 text-[0.8125rem] rounded-[0.65rem]',
        'md' => 'h-11 px-4.5 text-[0.9375rem] rounded-[var(--radius-control)]',
        'lg' => 'h-13 px-6 text-base rounded-[0.9rem]',
        'icon' => 'size-10 rounded-[var(--radius-control)]',
    ];
    $variants = [
        'primary' => 'bg-brand-700 text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.12),0_1px_2px_rgb(10_34_25/0.3)] hover:bg-brand-800',
        'secondary' => 'bg-surface text-ink border border-line-strong shadow-[var(--shadow-card)] hover:bg-sunken/60',
        'ghost' => 'text-ink-soft hover:bg-sunken',
        'danger' => 'bg-danger text-white hover:bg-[#9a1e14]',
        'danger-soft' => 'bg-danger-soft text-danger hover:bg-[#fbdcd8]',
        'gold' => 'bg-gold-300 text-brand-950 hover:bg-gold-400',
        'inverse' => 'bg-white/12 text-white ring-1 ring-white/20 hover:bg-white/20',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
    $iconClass = $size === 'sm' ? 'size-4' : 'size-[1.125rem]';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
        @if ($iconRight)<x-icon :name="$iconRight" :class="$iconClass" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if($busy) x-bind:disabled="busy" @endif>
        @if ($busy)
            <x-icon name="loader" class="{{ $iconClass }} animate-spin" x-show="busy" x-cloak />
        @endif
        @if ($icon)<x-icon :name="$icon" :class="$iconClass" x-show="{{ $busy ? '!busy' : 'true' }}" />@endif
        {{ $slot }}
        @if ($iconRight)<x-icon :name="$iconRight" :class="$iconClass" />@endif
    </button>
@endif
