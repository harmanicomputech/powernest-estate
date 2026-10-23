<x-layouts.app :title="$isManager || $isWorker ? 'Work orders' : 'Maintenance'">
    <x-ui.page-header :title="$isManager || $isWorker ? 'Work orders' : 'Maintenance'" :description="$isManager ? 'Every request in the estate, by priority.' : 'Report problems and follow progress.'">
        @can('maintenance.request')<x-ui.button :href="route('maintenance.create')" icon="plus">New request</x-ui.button>@endcan
    </x-ui.page-header>

    <x-ui.tabs :active="$tab" :tabs="array_filter([
        'open' => ['Open', route('maintenance.index'), $counts['open']],
        'mine' => $isWorker ? ['Assigned to me', route('maintenance.index', ['tab' => 'mine']), $counts['mine']] : null,
        'overdue' => $isManager ? ['Overdue', route('maintenance.index', ['tab' => 'overdue']), $counts['overdue']] : null,
        'done' => ['Completed', route('maintenance.index', ['tab' => 'done'])],
    ])" />

    <x-ui.card :padded="false">
        @forelse ($requests as $request)
            <a href="{{ route('maintenance.show', $request) }}" class="group flex items-center gap-4 px-5 py-4 transition hover:bg-canvas sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <span @class(['h-10 w-1 shrink-0 rounded-full', 'bg-danger' => $request->priority === \App\Enums\Priority::Urgent, 'bg-warning' => $request->priority === \App\Enums\Priority::High, 'bg-info' => $request->priority === \App\Enums\Priority::Normal, 'bg-line-strong' => $request->priority === \App\Enums\Priority::Low])></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $request->title }}</p>
                    <p class="truncate text-sm text-muted">{{ $request->reference }} · {{ $request->property?->identifier ?? $request->location }} · {{ $request->created_at->diffForHumans() }}@if($isManager || $isWorker) · {{ $request->assignee?->name ?? 'Unassigned' }}@endif</p>
                </div>
                @if ($request->isOverdue())<x-ui.badge tone="danger">Overdue</x-ui.badge>@endif
                <x-ui.status :value="$request->status" />
            </a>
        @empty
            <x-ui.empty icon="wrench" title="Nothing here" description="Maintenance requests will appear here as they come in." />
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-layouts.app>
