@props(['user', 'compact' => false, 'inline' => false, 'placement' => 'down'])
@if ($inline)
    <div class="flex min-w-0 items-center gap-3">
        <x-ui.avatar :user="$user" />
        <div class="min-w-0">
            <p class="truncate text-sm font-bold">{{ $user->name }}</p>
            <p class="truncate text-xs text-muted">{{ $user->email }}</p>
        </div>
    </div>
@else
<div x-data="{ open: false }" class="relative" x-on:click.outside="open = false">
    <button type="button" x-on:click="open = !open" @class(['flex items-center gap-3 rounded-xl text-left transition hover:bg-sunken', 'w-full p-2' => ! $compact, 'p-1' => $compact]) aria-haspopup="true" x-bind:aria-expanded="open">
        <x-ui.avatar :user="$user" :size="$compact ? 'sm' : 'md'" />
        @unless ($compact)
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-bold">{{ $user->name }}</span>
                <span class="block truncate text-xs text-muted">{{ $user->email }}</span>
            </span>
            <x-icon name="chevrons-up-down" class="size-4 text-muted" />
        @endunless
    </button>
    <div x-show="open" x-cloak x-transition @class(['absolute z-50 w-60 rounded-xl border border-line bg-surface p-1.5 shadow-[var(--shadow-raised)]', 'bottom-full left-0 mb-2' => $placement === 'up', 'right-0 top-full mt-2' => $placement !== 'up'])>
        <div class="px-2.5 py-2">
            <p class="truncate text-sm font-bold">{{ $user->name }}</p>
            <p class="truncate text-xs text-muted">{{ $user->email }}</p>
        </div>
        <div class="my-1 h-px bg-line"></div>
        <a href="{{ route('account.edit') }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold hover:bg-sunken"><x-icon name="circle-user-round" class="size-4 text-muted" />Account &amp; security</a>
        <a href="{{ route('notifications.index') }}" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold hover:bg-sunken"><x-icon name="bell" class="size-4 text-muted" />Notifications</a>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold text-danger hover:bg-danger-soft"><x-icon name="log-out" class="size-4" />Sign out</button>
        </form>
    </div>
</div>
@endif
