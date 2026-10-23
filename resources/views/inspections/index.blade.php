<x-layouts.app title="Inspections">
    <x-ui.page-header title="Inspections" description="Run safety and facility checklists. Failed items can raise a maintenance request automatically.">
        @if ($canManage)<x-ui.button type="button" variant="secondary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'template')">New checklist</x-ui.button>@endif
    </x-ui.page-header>

    @if ($inProgress->isNotEmpty())
        <x-ui.card title="In progress" class="mb-6" :padded="false">
            @foreach ($inProgress as $inspection)
                <x-ui.row-link :href="route('inspections.show', $inspection)" icon="clipboard-check" tone="info" class="border-t border-line">
                    <p class="font-semibold">{{ $inspection->title }}</p>
                    <p class="text-sm text-muted">{{ $inspection->location ?? 'No location' }} · started by {{ $inspection->inspector?->name }} {{ $inspection->created_at->diffForHumans() }}</p>
                </x-ui.row-link>
            @endforeach
        </x-ui.card>
    @endif

    <h2 class="mb-3 text-[0.9375rem] font-bold">Checklists</h2>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($templates as $template)
            <div class="card flex flex-col p-5" x-data="{ open: false }">
                <div class="flex items-center justify-between"><x-ui.badge>{{ $categories[$template->category] ?? humanize($template->category) }}</x-ui.badge><span class="text-xs text-muted">{{ count($template->items) }} items · run {{ $template->inspections_count }}×</span></div>
                <p class="mt-3 flex-1 font-bold">{{ $template->name }}</p>
                <x-ui.button type="button" class="mt-4" size="sm" icon="clipboard-check" x-show="!open" x-on:click="open = true">Start inspection</x-ui.button>
                <form method="POST" action="{{ route('inspections.start', $template) }}" x-show="open" x-cloak class="mt-4 flex gap-2">@csrf
                    <input name="location" class="control h-9 flex-1 py-1 text-sm" placeholder="Location (optional)">
                    <x-ui.button size="sm">Go</x-ui.button>
                </form>
            </div>
        @empty
            <x-ui.card class="sm:col-span-2 lg:col-span-3"><x-ui.empty icon="clipboard-check" title="No checklists yet" :description="$canManage ? 'Create a checklist such as a nightly perimeter patrol or monthly fire-safety check.' : 'Ask your supervisor to set up checklists.'" /></x-ui.card>
        @endforelse
    </div>

    <h2 class="mb-3 mt-8 text-[0.9375rem] font-bold">Completed</h2>
    <x-ui.card :padded="false">
        @forelse ($completed as $inspection)
            <x-ui.row-link :href="route('inspections.show', $inspection)" icon="clipboard-check" :tone="$inspection->failed_count ? 'danger' : 'success'" :class="$loop->first ? '' : 'border-t border-line'">
                <p class="font-semibold">{{ $inspection->title }}</p>
                <p class="text-sm text-muted">{{ local_time($inspection->completed_at, 'D j M, g:ia') }} · {{ $inspection->inspector?->name }}{{ $inspection->location ? ' · '.$inspection->location : '' }}</p>
                <x-slot:aside>@if ($inspection->failed_count)<x-ui.badge tone="danger">{{ $inspection->failed_count }} failed</x-ui.badge>@else<x-ui.badge tone="success">All clear</x-ui.badge>@endif</x-slot:aside>
            </x-ui.row-link>
        @empty
            <x-ui.empty icon="history" title="No inspections completed yet" />
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $completed->links() }}</div>

    @if ($canManage)
        <x-ui.modal name="template" title="New checklist">
            <form method="POST" action="{{ route('inspections.templates.store') }}" class="space-y-4">@csrf
                <x-ui.input name="name" label="Name" required placeholder="e.g. Nightly perimeter patrol" />
                <x-ui.select name="category" label="Category" :options="$categories" />
                <x-ui.textarea name="items" label="Checklist items" rows="6" required hint="One item per line." placeholder="Perimeter lights working&#10;Fence intact&#10;Fire extinguishers in date" />
                <x-ui.button class="w-full">Save checklist</x-ui.button>
            </form>
        </x-ui.modal>
    @endif
</x-layouts.app>
