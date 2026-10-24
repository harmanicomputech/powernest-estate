<x-layouts.app title="Electricity">
    <x-ui.page-header title="Electricity" description="Buy prepaid tokens for meters linked to your home. Powered by PowerNest.">
        <x-ui.button :href="route('power.history')" variant="secondary" icon="history">History</x-ui.button>
        <x-ui.button :href="route('power.buy')" icon="zap">Buy electricity</x-ui.button>
    </x-ui.page-header>

    @foreach ($attention as $purchase)
        <a href="{{ route('power.purchases.show', $purchase) }}" class="mb-4 flex items-center gap-4 rounded-[var(--radius-card)] border border-line bg-surface p-4 transition hover:border-line-strong">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $purchase->status->needsAttention() ? 'bg-warning-soft text-warning' : 'bg-info-soft text-info' }}"><x-icon :name="$purchase->status->needsAttention() ? 'triangle-alert' : 'loader'" class="size-5 {{ $purchase->status->needsAttention() ? '' : 'animate-spin' }}" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ money($purchase->vend_amount) }} for meter {{ $purchase->meter_number }}</p>
                <p class="text-sm text-muted">{{ $purchase->status->label() }} · {{ $purchase->created_at->diffForHumans() }}</p>
            </div>
            <x-icon name="chevron-right" class="size-5 text-faint" />
        </a>
    @endforeach

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Your meters" :padded="false">
                <x-slot:action><x-ui.button type="button" size="sm" variant="secondary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'add-meter')">Add meter</x-ui.button></x-slot:action>
                @forelse ($meters as $meter)
                    <div class="flex items-center gap-4 border-t border-line px-5 py-4 sm:px-6">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gold-50 text-gold-600"><x-icon name="plug-zap" class="size-5" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-semibold">{{ $meter->vendor_customer_name ?? $meter->label ?? 'Meter' }}</p>
                                @if ($meter->validated_at)
                                    <x-icon name="badge-check" class="size-4 text-brand-600" aria-label="Verified" />
                                @else
                                    <x-ui.badge tone="warning">Unverified</x-ui.badge>
                                @endif
                            </div>
                            <p class="truncate text-sm text-muted"><span class="font-mono tnum">{{ $meter->meter_number }}</span>{{ ($meter->vendor_address ?? $meter->property?->identifier) ? ' · '.($meter->vendor_address ?? $meter->property?->identifier) : '' }}</p>
                        </div>
                        <form method="POST" action="{{ route('power.meters.favourite', $meter) }}">@csrf
                            <button class="grid size-9 place-items-center rounded-lg hover:bg-sunken" aria-label="{{ $favourites->contains($meter->id) ? 'Remove from favourites' : 'Add to favourites' }}">
                                <x-icon name="star" @class(['size-[1.125rem]', 'fill-gold-300 text-gold-400' => $favourites->contains($meter->id), 'text-faint' => ! $favourites->contains($meter->id)]) />
                            </button>
                        </form>
                        <x-ui.button :href="route('power.buy', ['meter' => $meter->id])" size="sm">Buy</x-ui.button>
                    </div>
                @empty
                    <x-ui.empty icon="plug-zap" title="No meters yet" description="Enter a prepaid meter number to start buying electricity." class="border-t border-line">
                        <x-ui.button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'add-meter')">Add meter</x-ui.button>
                    </x-ui.empty>
                @endforelse
            </x-ui.card>

            <x-ui.card title="Recent purchases" :padded="false">
                <x-slot:action><a href="{{ route('power.history') }}" class="text-sm font-semibold text-brand-700 hover:underline">View all</a></x-slot:action>
                @forelse ($purchases as $purchase)
                    <x-ui.row-link :href="route('power.purchases.show', $purchase)" icon="zap" tone="gold" class="border-t border-line">
                        <p class="font-semibold tnum">{{ money($purchase->vend_amount) }} <span class="font-normal text-muted">· {{ $purchase->meter_number }}</span></p>
                        <p class="text-sm text-muted">{{ local_time($purchase->created_at, 'j M, g:ia') }}{{ $purchase->token?->units ? ' · '.$purchase->token->units.' kWh' : '' }}</p>
                        <x-slot:aside><x-ui.status :value="$purchase->status" /></x-slot:aside>
                    </x-ui.row-link>
                @empty
                    <p class="border-t border-line px-5 py-6 text-sm text-muted sm:px-6">You haven't bought electricity yet.</p>
                @endforelse
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.stat label="Spent this month" :value="money($monthSpend, false)" icon="wallet" tone="gold" hint="Electricity value of tokens issued" />
            <x-ui.alert tone="info" title="Safe by design">You'll only see a token once the vending service has issued it. If a payment goes through but a token doesn't, the estate team is alerted automatically — you won't be charged twice.</x-ui.alert>
        </div>
    </div>

    <x-ui.modal name="add-meter" title="Add a meter">
        @include('power.partials.add-meter')
    </x-ui.modal>
    @if ($errors->has('meter_number'))
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'add-meter'))"></div>
    @endif
</x-layouts.app>
