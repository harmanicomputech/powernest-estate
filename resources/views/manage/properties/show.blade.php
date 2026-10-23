<x-layouts.app :title="$property->identifier">
    <x-ui.page-header :title="$property->identifier" :eyebrow="$property->type->label().($property->building ? ' · '.$property->building->name : '')" :back="route('manage.properties.index')">
        <x-ui.status :value="$property->occupancy_status" />
    </x-ui.page-header>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Occupants" :padded="false">
                @forelse ($occupants as $occupancy)
                    <div class="flex items-center gap-3 border-t border-line px-5 py-3.5 sm:px-6">
                        <x-ui.avatar :user="$occupancy->user" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $occupancy->user->name }}</p>
                            <p class="truncate text-sm text-muted">{{ $occupancy->relationship->label() }}{{ $occupancy->primaryOccupancy ? ' of '.$occupancy->primaryOccupancy->user->name : '' }} · since {{ $occupancy->starts_on?->format('j M Y') }} · {{ $occupancy->user->email }}</p>
                        </div>
                        @can('residents.manage')
                            <x-ui.confirm :action="route('manage.occupancies.end', $occupancy)" method="DELETE" :title="'End occupancy for '.$occupancy->user->name.'?'" message="Their access to this property (and any household members they added) will end today. History is kept." confirm="End occupancy" confirm-variant="danger" size="sm" variant="ghost">End</x-ui.confirm>
                        @endcan
                    </div>
                @empty
                    <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">Vacant.</p>
                @endforelse
                @can('residents.manage')
                    <form method="POST" action="{{ route('manage.properties.occupants.store', $property) }}" class="grid gap-3 border-t border-line p-5 sm:grid-cols-4 sm:p-6">@csrf
                        <div class="sm:col-span-2"><x-ui.input name="email" type="email" placeholder="Email address" required /></div>
                        <x-ui.select name="relationship" :options="['tenant' => 'Tenant', 'owner' => 'Homeowner', 'landlord' => 'Landlord (non-resident)']" />
                        <x-ui.button icon="user-plus">Add</x-ui.button>
                        <p class="text-xs text-muted sm:col-span-4">Existing users are linked immediately; new people receive an invitation email.</p>
                    </form>
                @endcan
            </x-ui.card>

            @if ($history->isNotEmpty())
                <x-ui.card title="Occupancy history" :padded="false">
                    @foreach ($history as $past)
                        <div class="flex justify-between border-t border-line px-5 py-3 text-sm sm:px-6"><span>{{ $past->user->name }} · {{ $past->relationship->label() }}</span><span class="text-muted">{{ $past->starts_on?->format('M Y') }} – {{ $past->ends_on?->format('M Y') }}</span></div>
                    @endforeach
                </x-ui.card>
            @endif

            <x-ui.card title="Recent invoices" :padded="false">
                <x-slot:action><span class="text-sm">Balance <span class="font-bold tnum">{{ money($balance) }}</span></span></x-slot:action>
                @forelse ($invoices as $invoice)
                    <a href="{{ route('bills.show', $invoice) }}" class="flex items-center justify-between border-t border-line px-5 py-3 text-sm hover:bg-canvas sm:px-6"><span>{{ $invoice->number }} · {{ $invoice->description }}</span><span class="flex items-center gap-2"><span class="font-semibold tnum">{{ money($invoice->total) }}</span><x-ui.status :value="$invoice->status" /></span></a>
                @empty
                    <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">No invoices.</p>
                @endforelse
            </x-ui.card>
        </div>
        <div class="space-y-6">
            @can('properties.manage')
                <x-ui.card title="Details">
                    <form method="POST" action="{{ route('manage.properties.update', $property) }}" class="space-y-4">@csrf @method('PUT')
                        <x-ui.select name="building_id" label="Building" :options="$buildings->pluck('name', 'id')->all()" :value="$property->building_id" placeholder="None" />
                        <x-ui.select name="type" label="Type" :options="\App\Enums\PropertyType::options()" :value="$property->type" />
                        <div class="grid grid-cols-2 gap-3"><x-ui.input name="bedrooms" type="number" label="Bedrooms" :value="$property->bedrooms" /><x-ui.input name="floor" label="Floor" :value="$property->floor" /></div>
                        <x-ui.input name="address_line" label="Address" :value="$property->address_line" />
                        <x-ui.checkbox name="under_renovation" label="Under renovation" :checked="$property->occupancy_status === \App\Enums\OccupancyStatus::UnderRenovation" />
                        <x-ui.button variant="secondary" class="w-full">Save</x-ui.button>
                    </form>
                </x-ui.card>
            @endcan
            <x-ui.card title="Meters" :padded="false">
                @forelse ($property->meters as $meter)
                    <div class="flex justify-between border-t border-line px-5 py-3 text-sm"><span class="font-mono">{{ $meter->meter_number }}</span><span class="text-muted">{{ $meter->label }}</span></div>
                @empty
                    <p class="border-t border-line px-5 py-4 text-sm text-muted">No meters registered.</p>
                @endforelse
            </x-ui.card>
            @can('billing.manage')<x-ui.button :href="route('manage.billing.invoices.create', ['property' => $property->id])" variant="secondary" class="w-full" icon="receipt">Create invoice</x-ui.button>@endcan
        </div>
    </div>
</x-layouts.app>
