@props(['tone' => 'neutral', 'dot' => false])
@php
    $tones = [
        'neutral' => 'bg-sunken text-ink-soft ring-line-strong/60',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-200',
        'success' => 'bg-success-soft text-success ring-[#bfe5cf]',
        'warning' => 'bg-warning-soft text-warning ring-[#f3d9a8]',
        'danger' => 'bg-danger-soft text-danger ring-[#f6c7c1]',
        'info' => 'bg-info-soft text-info ring-[#c6d8f2]',
        'gold' => 'bg-gold-50 text-gold-600 ring-gold-200',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset whitespace-nowrap', $tones[$tone] ?? $tones['neutral']]) }}>
    @if ($dot)<span class="size-1.5 rounded-full bg-current"></span>@endif
    {{ $slot }}
</span>
