<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" wire:poll.30s.visible>
    <button type="button" x-on:click="open = !open" class="relative grid size-10 place-items-center rounded-xl text-ink-soft transition hover:bg-sunken" aria-label="Notifications{{ $unread ? ", $unread unread" : '' }}">
        <x-icon name="bell" class="size-5" />
        @if ($unread)
            <span class="absolute right-1.5 top-1.5 grid min-w-[1.125rem] place-items-center rounded-full bg-danger px-1 text-[0.625rem] font-bold leading-[1.125rem] text-white ring-2 ring-canvas tnum">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak x-transition.origin.top.right class="fixed inset-x-3 top-[calc(env(safe-area-inset-top)+4.25rem)] z-50 rounded-2xl border border-line bg-surface shadow-[var(--shadow-raised)] sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-[23rem]">
        <div class="flex items-center justify-between px-4 pb-2 pt-3.5">
            <p class="text-sm font-bold">Notifications</p>
            @if ($unread)
                <button type="button" wire:click="markAllRead" class="text-xs font-semibold text-brand-700 hover:underline">Mark all read</button>
            @endif
        </div>
        <div class="max-h-[60dvh] divide-y divide-line overflow-y-auto">
            @forelse ($latest as $notification)
                <a href="{{ route('notifications.open', $notification->id) }}" class="flex gap-3 px-4 py-3 hover:bg-canvas">
                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700"><x-icon :name="$notification->data['icon'] ?? 'bell'" class="size-4" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-2">
                            <span class="text-sm font-semibold {{ $notification->read_at ? 'text-ink-soft' : 'text-ink' }}">{{ $notification->data['title'] ?? 'Update' }}</span>
                            @unless ($notification->read_at)<span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500"></span>@endunless
                        </span>
                        <span class="line-clamp-2 text-[0.8125rem] text-muted">{{ $notification->data['body'] ?? '' }}</span>
                        <span class="mt-0.5 block text-xs text-faint">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <x-ui.empty icon="bell" title="You're all caught up" description="Updates about visitors, payments and requests will appear here." class="!py-8" />
            @endforelse
        </div>
        <a href="{{ route('notifications.index') }}" class="block border-t border-line px-4 py-3 text-center text-sm font-semibold text-brand-700 hover:bg-canvas">View all</a>
    </div>
</div>
