@props(['estate', 'estates', 'roleLabel' => null])
<div x-data="{ open: false }" class="relative" x-on:click.outside="open = false">
    <button type="button" x-on:click="open = !open" class="flex w-full items-center gap-3 rounded-xl border border-line bg-canvas px-3 py-2.5 text-left transition hover:border-line-strong" aria-haspopup="true" x-bind:aria-expanded="open">
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-800 text-[0.8125rem] font-extrabold text-gold-200">{{ mb_substr($estate->name, 0, 1) }}</span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-bold">{{ $estate->name }}</span>
            <span class="block truncate text-xs text-muted">{{ $roleLabel }}</span>
        </span>
        @if ($estates->count() > 1)<x-icon name="chevrons-up-down" class="size-4 text-muted" />@endif
    </button>
    @if ($estates->count() > 1)
        <div x-show="open" x-cloak x-transition.origin.top class="absolute inset-x-0 top-full z-50 mt-2 rounded-xl border border-line bg-surface p-1.5 shadow-[var(--shadow-raised)]">
            <p class="eyebrow px-2.5 pb-1 pt-1.5">Switch estate</p>
            @foreach ($estates as $option)
                <form method="POST" action="{{ route('estates.switch', $option) }}">@csrf
                    <button class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-sm font-semibold hover:bg-sunken">
                        <span class="truncate">{{ $option->name }}</span>
                        @if ($option->is($estate))<x-icon name="check" class="size-4 text-brand-700" />@endif
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</div>
