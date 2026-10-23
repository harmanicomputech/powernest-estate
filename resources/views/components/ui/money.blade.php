@props(['amount', 'compact' => false])
<span {{ $attributes->class('tnum') }}>{{ $compact ? \App\Support\Money::compact($amount) : \App\Support\Money::format($amount) }}</span>
