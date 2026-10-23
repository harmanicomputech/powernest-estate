@props(['padded' => true, 'title' => null, 'description' => null, 'action' => null])
<section {{ $attributes->class(['card', 'card-pad' => $padded && ! $title]) }}>
    @if ($title)
        <header class="flex items-start justify-between gap-4 px-5 pt-5 sm:px-6 sm:pt-6 {{ $padded ? '' : 'pb-4' }}">
            <div class="min-w-0">
                <h2 class="text-[0.9375rem] font-bold text-ink">{{ $title }}</h2>
                @if ($description)<p class="mt-0.5 text-sm text-muted">{{ $description }}</p>@endif
            </div>
            @if ($action)<div class="shrink-0">{{ $action }}</div>@endif
        </header>
        <div @class(['px-5 pb-5 pt-4 sm:px-6 sm:pb-6' => $padded])>{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</section>
