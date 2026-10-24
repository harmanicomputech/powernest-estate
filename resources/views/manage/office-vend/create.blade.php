<x-layouts.app title="Issue electricity token">
    <x-ui.page-header title="Issue electricity token" description="Vend a token without payment. The meter's main occupant gets it by email, and you can print or download it." />

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <x-ui.card title="1. Find the meter">
                <form method="GET" class="flex gap-2">
                    <div class="flex-1"><x-ui.input name="meter" :value="$number" inputmode="numeric" placeholder="Meter number, e.g. 58103812218" required /></div>
                    <x-ui.button variant="secondary" icon="search">Find</x-ui.button>
                </form>
                @if ($number !== '' && ! $meter)
                    <x-ui.alert tone="warning" class="mt-4">{{ $lookupError ?? 'Meter '.$number.' could not be found.' }}</x-ui.alert>
                @endif
            </x-ui.card>

            @if ($meter)
                <x-ui.card title="2. Amount">
                    <div class="mb-5 flex items-center gap-3 rounded-xl bg-canvas p-3.5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gold-50 text-gold-600"><x-icon name="zap" class="size-5" /></span>
                        <div class="min-w-0 text-sm">
                            <p class="font-mono font-bold">{{ $meter->meter_number }}</p>
                            <p class="text-muted">{{ $meter->property?->identifier ?? $meter->vendor_address ?? 'Not linked to a home' }}{{ $meter->vendor_customer_name ? ' · '.$meter->vendor_customer_name : '' }}</p>
                            <p class="text-muted">Token goes to: <span class="font-semibold text-ink">{{ $owner ? $owner->name.' ('.$owner->email.')' : 'you (no one lives here yet)' }}</span></p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('manage.office-vend.store') }}" class="space-y-4">@csrf
                        <input type="hidden" name="meter_id" value="{{ $meter->id }}">
                        <x-ui.input name="amount" label="Electricity value" prefix="₦" inputmode="decimal" placeholder="5,000" :hint="'Up to '.money($max, false).'. No service charge is added.'" required />
                        <x-ui.input name="note" label="Reason" placeholder="e.g. Paid cash at the office, compensation for outage" required />
                        <x-ui.button size="lg" icon="zap" class="w-full" onclick="return confirm('Issue a real token on meter {{ $meter->meter_number }} without payment? This is recorded in the audit log.')">Issue token</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <x-ui.card title="Recently issued by the office" :padded="false" class="lg:col-span-2">
            @forelse ($recent as $purchase)
                <x-ui.row-link :href="route('manage.office-vend.show', $purchase)" icon="zap" tone="gold" :class="$loop->first ? '' : 'border-t border-line'">
                    <p class="font-semibold tnum">{{ money($purchase->vend_amount) }} · {{ $purchase->meter_number }}</p>
                    <p class="truncate text-sm text-muted">{{ $purchase->user->name }} · by {{ $purchase->issuer?->name }} · {{ $purchase->created_at->diffForHumans() }}</p>
                </x-ui.row-link>
            @empty
                <p class="px-5 pb-5 text-sm text-muted sm:px-6">Nothing issued yet.</p>
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.app>
