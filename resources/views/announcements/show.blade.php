<x-layouts.app :title="$announcement->title">
    <x-ui.page-header :title="$announcement->title" :back="route('announcements.index')" />
    <article class="card mx-auto max-w-3xl p-6 sm:p-10">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge :tone="$announcement->category->tone()">{{ $announcement->category->label() }}</x-ui.badge>
            <span class="text-sm text-muted">{{ $announcement->author?->name }} · {{ local_time($announcement->published_at, 'j F Y, g:ia') }}</span>
        </div>
        <div class="mt-6 whitespace-pre-line text-[1.0625rem] leading-relaxed text-ink-soft">{{ $announcement->body }}</div>
        @if ($announcement->expires_at)<p class="mt-8 text-xs text-faint">Visible until {{ local_time($announcement->expires_at, 'j M Y') }}</p>@endif
    </article>
</x-layouts.app>
