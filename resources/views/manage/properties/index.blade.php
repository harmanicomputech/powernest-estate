<x-layouts.app title="Properties" wide>
    <x-ui.page-header title="Properties" description="Every unit in the estate, its occupancy and who lives there.">
        @can('properties.manage')
            <x-ui.button type="button" variant="secondary" icon="layers" x-data x-on:click="$dispatch('open-modal', 'building')">Add building</x-ui.button>
            <x-ui.button :href="route('manage.properties.create')" icon="plus">Add property</x-ui.button>
        @endcan
    </x-ui.page-header>

    @if ($moveRequests->isNotEmpty())
        <x-ui.card title="Move requests" class="mb-6" :padded="false">
            @foreach ($moveRequests as $move)
                <div class="flex flex-wrap items-center gap-3 border-t border-line px-5 py-3 text-sm sm:px-6">
                    <span class="flex-1"><span class="font-semibold">{{ $move->user->name }}</span> · {{ $move->type === 'move_in' ? 'moving in to' : 'moving out of' }} {{ $move->property->identifier }} on {{ $move->scheduled_for->format('j M') }}</span>
                    @can('residents.manage')
                        <form method="POST" action="{{ route('manage.move-requests.review', $move) }}" class="flex gap-1.5">@csrf
                            <x-ui.button name="decision" value="rejected" size="sm" variant="ghost">Decline</x-ui.button>
                            <x-ui.button name="decision" value="approved" size="sm">Approve</x-ui.button>
                        </form>
                    @endcan
                </div>
            @endforeach
        </x-ui.card>
    @endif

    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach (\App\Enums\OccupancyStatus::cases() as $s)
            <a href="{{ route('manage.properties.index', ['status' => $status === $s->value ? null : $s->value]) }}" @class(['card flex items-center justify-between p-4 transition hover:border-line-strong', 'ring-2 ring-brand-500' => $status === $s->value])>
                <span class="text-sm font-semibold text-muted">{{ $s->label() }}</span><span class="text-xl font-extrabold tnum">{{ $counts[$s->value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <form class="mb-4 flex flex-wrap gap-2">
        <div class="min-w-[12rem] flex-1"><x-ui.input name="q" :value="$search" placeholder="Search unit or address" /></div>
        <div class="w-48"><x-ui.select name="building" :options="$buildings->pluck('name', 'id')->all()" :value="$building" placeholder="All buildings" onchange="this.form.submit()" /></div>
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <x-ui.button variant="secondary" icon="search">Filter</x-ui.button>
    </form>

    <x-ui.card :padded="false" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-wide text-muted">
                    <tr><th class="px-5 py-3">Unit</th><th class="px-3 py-3">Building</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Primary occupant</th><th class="px-5 py-3 text-right">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($properties as $property)
                        <tr class="cursor-pointer transition hover:bg-canvas" onclick="location.href='{{ route('manage.properties.show', $property) }}'">
                            <td class="px-5 py-3.5 font-bold"><a href="{{ route('manage.properties.show', $property) }}">{{ $property->identifier }}</a></td>
                            <td class="px-3 py-3.5 text-muted">{{ $property->building?->name ?? '—' }}</td>
                            <td class="px-3 py-3.5">{{ $property->type->label() }}</td>
                            <td class="px-3 py-3.5">{{ $property->occupancies->map(fn ($o) => $o->user->name)->join(', ') ?: '—' }}</td>
                            <td class="px-5 py-3.5 text-right"><x-ui.status :value="$property->occupancy_status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty icon="building-2" title="No properties" description="Add buildings and units to start onboarding residents." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
    <div class="mt-4">{{ $properties->links() }}</div>

    <x-ui.modal name="building" title="Add building or street">
        <form method="POST" action="{{ route('manage.buildings.store') }}" class="space-y-4">@csrf
            <x-ui.input name="name" label="Name" required placeholder="e.g. Block C or Palm Close" />
            <div class="grid grid-cols-2 gap-3">
                <x-ui.input name="code" label="Code" required placeholder="C" class="uppercase" />
                <x-ui.select name="type" label="Type" :options="['block' => 'Block', 'street' => 'Street', 'zone' => 'Zone', 'tower' => 'Tower']" />
            </div>
            <x-ui.input name="floors" type="number" label="Floors" optional />
            <x-ui.button class="w-full">Add</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
