<x-layouts.app title="Notifications">
    <x-ui.page-header title="Notifications">
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<x-ui.button variant="secondary" icon="check-check">Mark all read</x-ui.button></form>
    </x-ui.page-header>
    <x-ui.card :padded="false">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}" class="flex gap-4 px-5 py-4 transition hover:bg-canvas sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-icon :name="$notification->data['icon'] ?? 'bell'" class="size-[1.125rem]" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold {{ $notification->read_at ? 'text-ink-soft' : '' }}">{{ $notification->data['title'] ?? 'Update' }}</p>
                    <p class="text-sm text-muted">{{ $notification->data['body'] ?? '' }}</p>
                    <p class="mt-1 text-xs text-faint">{{ local_time($notification->created_at, 'j M Y, g:ia') }}</p>
                </div>
                @unless ($notification->read_at)<span class="mt-2 size-2.5 shrink-0 rounded-full bg-brand-500" aria-label="Unread"></span>@endunless
            </a>
        @empty
            <x-ui.empty icon="bell" title="No notifications yet" />
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.app>
