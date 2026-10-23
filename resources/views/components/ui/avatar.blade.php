@props(['user' => null, 'name' => null, 'size' => 'md'])
@php
    $label = $user?->name ?? $name ?? '?';
    $initials = collect(explode(' ', $label))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $sizes = ['sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-14 text-lg'];
    $palette = ['bg-brand-100 text-brand-800', 'bg-gold-100 text-gold-600', 'bg-info-soft text-info', 'bg-[#efe7f6] text-[#6b3fa0]', 'bg-[#fde8dd] text-[#a2461c]'];
    $tone = $palette[crc32($label) % count($palette)];
@endphp
<span {{ $attributes->class("inline-grid shrink-0 place-items-center rounded-full font-bold {$sizes[$size]} $tone") }} aria-hidden="true">{{ $initials }}</span>
