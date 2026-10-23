@props(['tone' => 'info', 'title' => null, 'icon' => null])
@php
    $map = [
        'info' => ['bg-info-soft border-[#c6d8f2] text-info', 'info'],
        'success' => ['bg-success-soft border-[#bfe5cf] text-success', 'circle-check'],
        'warning' => ['bg-warning-soft border-[#f3d9a8] text-warning', 'triangle-alert'],
        'danger' => ['bg-danger-soft border-[#f6c7c1] text-danger', 'circle-x'],
        'brand' => ['bg-brand-50 border-brand-200 text-brand-700', 'sparkles'],
    ];
    [$classes, $defaultIcon] = $map[$tone] ?? $map['info'];
@endphp
<div role="{{ in_array($tone, ['danger', 'warning']) ? 'alert' : 'status' }}" {{ $attributes->class("flex gap-3 rounded-[var(--radius-control)] border px-4 py-3 text-sm $classes") }}>
    <x-icon :name="$icon ?? $defaultIcon" class="mt-0.5 size-[1.125rem] shrink-0" />
    <div class="min-w-0 text-ink-soft">
        @if ($title)<p class="font-bold text-current">{{ $title }}</p>@endif
        <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
    </div>
</div>
