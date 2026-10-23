@props(['inverse' => false])
<span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
    <x-brand.mark class="size-8 shrink-0" />
    <span class="leading-none">
        <span @class(['block text-[0.9375rem] font-extrabold tracking-tight', 'text-white' => $inverse, 'text-ink' => ! $inverse])>PowerNest</span>
        <span @class(['block text-[0.625rem] font-bold uppercase tracking-[0.2em]', 'text-gold-300' => $inverse, 'text-gold-500' => ! $inverse])>Estate</span>
    </span>
</span>
