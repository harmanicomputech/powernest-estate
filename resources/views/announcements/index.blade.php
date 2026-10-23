<x-layouts.app title="Announcements">
    <x-ui.page-header title="Announcements" description="News and notices from estate management.">
        @can('announcements.publish')<x-ui.button :href="route('announcements.create')" icon="megaphone">Publish</x-ui.button>@endcan
    </x-ui.page-header>
    <div class="space-y-3">
        @forelse ($announcements as $announcement)
            <a href="{{ route('announcements.show', $announcement) }}" @class(['card block p-5 transition hover:shadow-[var(--shadow-raised)] sm:p-6', 'border-danger/40' => $announcement->category === \App\Enums\AnnouncementCategory::Emergency])>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($announcement->is_pinned)<x-ui.badge tone="gold"><x-icon name="pin" class="size-3" />Pinned</x-ui.badge>@endif
                    <x-ui.badge :tone="$announcement->category->tone()">{{ $announcement->category->label() }}</x-ui.badge>
                    @if ($announcement->audience === 'building')<x-ui.badge>{{ $announcement->building?->name }}</x-ui.badge>@endif
                    @unless ($readIds->contains($announcement->id))<span class="size-2 rounded-full bg-brand-500" aria-label="Unread"></span>@endunless
                    <span class="ml-auto text-xs text-faint">{{ $announcement->published_at->diffForHumans() }}</span>
                </div>
                <h2 class="mt-2.5 text-lg font-bold">{{ $announcement->title }}</h2>
                <p class="mt-1 line-clamp-2 text-sm text-muted">{{ $announcement->body }}</p>
            </a>
        @empty
            <x-ui.card><x-ui.empty icon="megaphone" title="No announcements" description="Estate notices will appear here." /></x-ui.card>
        @endforelse
    </div>
    <div class="mt-4">{{ $announcements->links() }}</div>
</x-layouts.app>
