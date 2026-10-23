<section class="space-y-6" aria-label="Facilities">
    <div class="flex items-end justify-between">
        <h2 class="text-lg font-extrabold">Maintenance</h2>
        <x-ui.button :href="route('maintenance.index')" variant="secondary" icon="hammer">Work orders</x-ui.button>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Open" :value="$data['open']" icon="wrench" :href="route('maintenance.index')" />
        <x-ui.stat label="Urgent / high" :value="$data['urgent']" icon="siren" tone="danger" />
        <x-ui.stat label="Overdue" :value="$data['overdue']" icon="hourglass" tone="warning" :href="route('maintenance.index', ['tab' => 'overdue'])" />
        <x-ui.stat label="Done (30 days)" :value="$data['completed30']" icon="circle-check" tone="info" :hint="$data['unassigned'].' awaiting assignment'" />
    </div>
    <div class="grid gap-6 lg:grid-cols-5">
        <x-ui.card title="Priority queue" :padded="false" class="lg:col-span-3">
            @forelse ($data['queue'] as $request)
                <x-ui.row-link :href="route('maintenance.show', $request)" icon="wrench" :tone="$request->priority->tone()" class="border-t border-line">
                    <p class="truncate text-sm font-semibold">{{ $request->title }}</p>
                    <p class="truncate text-xs text-muted">{{ $request->property?->identifier ?? $request->location }} · {{ $request->assignee?->name ?? 'Unassigned' }}@if($request->isOverdue()) · <span class="font-semibold text-danger">Overdue</span>@endif</p>
                    <x-slot:aside><x-ui.status :value="$request->status" /></x-slot:aside>
                </x-ui.row-link>
            @empty
                <x-ui.empty icon="circle-check" title="Queue is clear" class="border-t border-line !py-8" />
            @endforelse
        </x-ui.card>
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Service due (14 days)" :padded="false">
                @forelse ($data['assets'] as $asset)
                    <div class="flex items-center justify-between border-t border-line px-5 py-3 text-sm sm:px-6">
                        <span class="font-semibold">{{ $asset->name }}</span>
                        <span @class(['text-xs font-semibold', 'text-danger' => $asset->next_service_due_on->isPast(), 'text-muted' => ! $asset->next_service_due_on->isPast()])>{{ $asset->next_service_due_on->format('j M') }}</span>
                    </div>
                @empty
                    <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">No assets due for service.</p>
                @endforelse
            </x-ui.card>
            <x-ui.card title="My tasks" :padded="false">
                @forelse ($data['myTasks'] as $task)
                    <div class="flex items-center justify-between gap-3 border-t border-line px-5 py-3 text-sm sm:px-6">
                        <span class="truncate font-semibold">{{ $task->title }}</span>
                        <x-ui.status :value="$task->priority" />
                    </div>
                @empty
                    <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">No open tasks.</p>
                @endforelse
            </x-ui.card>
        </div>
    </div>
</section>
