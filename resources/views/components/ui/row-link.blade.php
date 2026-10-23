@props(['href', 'icon' => null, 'tone' => 'brand'])
@php $tones = ['brand' => 'bg-brand-50 text-brand-700', 'gold' => 'bg-gold-50 text-gold-600', 'danger' => 'bg-danger-soft text-danger', 'warning' => 'bg-warning-soft text-warning', 'info' => 'bg-info-soft text-info', 'neutral' => 'bg-sunken text-ink-soft', 'success' => 'bg-success-soft text-success']; @endphp
<a href="{{ $href }}" data-ripple data-press="tint" {{ $attributes->class('group flex items-center gap-3.5 px-5 py-3.5 transition hover:bg-canvas sm:px-6') }}>
    @if ($icon)
        <span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}"><x-icon :name="$icon" class="size-[1.125rem]" /></span>
    @endif
    <div class="min-w-0 flex-1">{{ $slot }}</div>
    @isset($aside)<div class="shrink-0 text-right">{{ $aside }}</div>@endisset
    <x-icon name="chevron-right" class="btn-icon size-4 shrink-0 text-faint transition group-hover:translate-x-0.5 group-hover:text-ink-soft" />
    <x-icon name="loader" class="btn-spinner size-4 shrink-0 animate-spin text-brand-600" />
</a>
