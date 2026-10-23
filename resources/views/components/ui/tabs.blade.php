@props(['tabs' => [], 'active' => null])
{{-- Link-based tabs: $tabs = [key => [label, url, count?]] --}}
<nav {{ $attributes->class('scroll-x -mx-4 mb-5 flex gap-1 px-4 sm:mx-0 sm:px-0') }} aria-label="Filters">
    @foreach ($tabs as $key => $tab)
        <a href="{{ $tab[1] }}" @class([
            'inline-flex h-9 items-center gap-2 whitespace-nowrap rounded-full px-3.5 text-sm font-semibold transition',
            'bg-ink text-white' => $active === $key,
            'text-muted hover:bg-sunken hover:text-ink' => $active !== $key,
        ]) @if($active === $key) aria-current="page" @endif>
            {{ $tab[0] }}
            @if (isset($tab[2]) && $tab[2] !== null)
                <span @class(['tnum rounded-full px-1.5 text-xs', 'bg-white/20' => $active === $key, 'bg-sunken text-ink-soft' => $active !== $key])>{{ $tab[2] }}</span>
            @endif
        </a>
    @endforeach
</nav>
