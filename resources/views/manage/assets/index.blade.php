<x-layouts.app title="Assets">
    <x-ui.page-header title="Shared assets" description="Equipment the estate maintains, with preventive service schedules.">
        <x-ui.button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'asset')">Add asset</x-ui.button>
    </x-ui.page-header>
    <x-ui.card :padded="false">
        @forelse ($assets as $asset)
            @php $due = $asset->next_service_due_on; @endphp
            <div class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <span class="grid size-10 place-items-center rounded-xl bg-sunken text-ink-soft"><x-icon name="plug-zap" class="size-[1.125rem]" /></span>
                <div class="min-w-0 flex-1"><p class="font-semibold">{{ $asset->name }}</p><p class="text-sm text-muted">{{ humanize($asset->category) }}{{ $asset->location ? ' · '.$asset->location : '' }}{{ $asset->service_interval_days ? ' · every '.$asset->service_interval_days.' days' : '' }}{{ $asset->maintenance_requests_count ? ' · '.$asset->maintenance_requests_count.' open work orders' : '' }}</p></div>
                @if ($due)<x-ui.badge :tone="$due->isPast() ? 'danger' : ($due->diffInDays(now()) < 14 ? 'warning' : 'neutral')">Service {{ $due->isPast() ? 'overdue' : 'due' }} {{ $due->format('j M') }}</x-ui.badge>@endif
                <form method="POST" action="{{ route('manage.assets.service', $asset) }}">@csrf<x-ui.button size="sm" variant="secondary" icon="wrench">Raise service order</x-ui.button></form>
            </div>
        @empty
            <x-ui.empty icon="plug-zap" title="No assets" description="Add generators, pumps, gates and other shared equipment to schedule preventive maintenance." />
        @endforelse
    </x-ui.card>
    <x-ui.modal name="asset" title="Add asset">
        <form method="POST" action="{{ route('manage.assets.store') }}" class="space-y-4">@csrf
            <x-ui.input name="name" label="Name" required />
            <div class="grid grid-cols-2 gap-3"><x-ui.select name="category" label="Category" :options="collect(['generator', 'transformer', 'borehole', 'pump', 'gate', 'cctv', 'lighting', 'elevator', 'other'])->mapWithKeys(fn ($c) => [$c => humanize($c)])->all()" /><x-ui.input name="location" label="Location" optional /></div>
            <div class="grid grid-cols-2 gap-3"><x-ui.input name="service_interval_days" type="number" label="Service every (days)" optional /><x-ui.input name="last_serviced_on" type="date" label="Last serviced" optional /></div>
            <x-ui.input name="serial_number" label="Serial number" optional />
            <x-ui.button class="w-full">Add asset</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
