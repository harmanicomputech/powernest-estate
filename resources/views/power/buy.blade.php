<x-layouts.app title="Buy electricity">
    <x-ui.page-header title="Buy electricity" :back="route('power.index')" />

    @if ($meters->isEmpty())
        <x-ui.card class="mx-auto max-w-lg">
            <x-ui.empty icon="plug-zap" title="Add a meter first" description="Enter the prepaid meter number on your property.">
                <div class="w-full max-w-sm text-left">@include('power.partials.add-meter')</div>
            </x-ui.empty>
        </x-ui.card>
    @elseif (empty($gateways))
        <x-ui.alert tone="warning" title="Payments are not available" class="mx-auto max-w-lg">Online payment hasn't been enabled for this estate yet. Please contact the estate office.</x-ui.alert>
    @else
        <form method="POST" action="{{ route('power.purchases.store') }}"
              x-data="{ amount: '{{ old('amount', '') }}', charge: {{ $serviceCharge }} }" x-on:submit="if ($el.dataset.busy) { $event.preventDefault() } else { $el.dataset.busy = 1 }"
              class="grid gap-6 lg:grid-cols-5">
            @csrf
            <div class="space-y-6 lg:col-span-3">
                <x-ui.card title="1. Meter">
                    <div class="space-y-2">
                        @foreach ($meters as $meter)
                            <label class="flex cursor-pointer items-center gap-4 rounded-xl border border-line-strong p-4 transition has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/60 has-[:focus-visible]:shadow-[var(--shadow-focus)]">
                                <input type="radio" name="meter_id" value="{{ $meter->id }}" class="size-4 accent-brand-700" @checked((int) old('meter_id', $selected ?: $meters->first()->id) === $meter->id)>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold">{{ $meter->label }}</span>
                                    <span class="block font-mono text-sm text-muted tnum">{{ $meter->meter_number }} · {{ $meter->property->identifier }}</span>
                                </span>
                                @if ($meter->vendor_customer_name)<span class="hidden text-right text-xs text-muted sm:block">{{ $meter->vendor_customer_name }}<br>{{ $meter->meter_type }}</span>@endif
                            </label>
                        @endforeach
                    </div>
                    @error('meter_id')<p class="mt-2 text-[0.8125rem] font-medium text-danger">{{ $message }}</p>@enderror
                    <button type="button" x-data x-on:click="$dispatch('open-modal', 'add-meter')" class="mt-3 text-sm font-semibold text-brand-700 hover:underline">+ Add another meter</button>
                </x-ui.card>

                <x-ui.card title="2. Amount">
                    <x-ui.input name="amount" label="Electricity value" prefix="₦" inputmode="decimal" x-model="amount" autocomplete="off" required class="text-lg font-bold tnum" :hint="'Between '.money($min, false).' and '.money($max, false).'.'" />
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        @foreach ([2000, 5000, 10000, 20000] as $preset)
                            @if ($preset * 100 >= $min && $preset * 100 <= $max)
                                <button type="button" x-on:click="amount = '{{ $preset }}'" class="h-10 rounded-xl border border-line-strong text-sm font-semibold transition hover:border-brand-500" :class="amount == '{{ $preset }}' && 'border-brand-600 bg-brand-50 text-brand-800'">₦{{ number_format($preset) }}</button>
                            @endif
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card title="3. Pay with">
                    <div class="space-y-2">
                        @foreach ($gateways as $key => $gateway)
                            <label class="flex cursor-pointer items-center gap-4 rounded-xl border border-line-strong p-4 transition has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/60">
                                <input type="radio" name="gateway" value="{{ $key }}" class="size-4 accent-brand-700" @checked(old('gateway', array_key_first($gateways)) === $key)>
                                <span class="flex-1 font-semibold">{{ $gateway->label() }}</span>
                                <x-icon :name="$key === 'payaza' ? 'landmark' : 'credit-card'" class="size-5 text-muted" />
                            </label>
                        @endforeach
                    </div>
                </x-ui.card>
            </div>

            <div class="lg:col-span-2">
                <div class="card card-pad lg:sticky lg:top-24">
                    <h2 class="text-[0.9375rem] font-bold">Summary</h2>
                    @php $toKobo = "(() => { const v = String(amount).replace(/[^0-9.]/g, ''); if (!v) return 0; const [w, f = ''] = v.split('.'); return parseInt(w || '0', 10) * 100 + parseInt((f + '00').slice(0, 2), 10); })()"; @endphp
                    <dl class="mt-4 space-y-3 text-sm" x-data="{ fmt: (k) => '₦' + (k / 100).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }">
                        <div class="flex justify-between"><dt class="text-muted">Electricity</dt><dd class="font-semibold tnum" x-text="fmt({{ $toKobo }})">₦0.00</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Service charge</dt><dd class="font-semibold tnum">{{ money($serviceCharge) }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-3 text-base"><dt class="font-bold">Total to pay</dt><dd class="font-extrabold tnum" x-text="fmt({{ $toKobo }} + charge)">{{ money($serviceCharge) }}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs text-muted">This is an estimate. The final amount is calculated and confirmed by the server before payment. Units are determined by the vending service.</p>
                    <x-ui.button class="mt-5 w-full" size="lg" icon="lock">Continue to payment</x-ui.button>
                </div>
            </div>
        </form>

        <x-ui.modal name="add-meter" title="Add a meter">@include('power.partials.add-meter')</x-ui.modal>
    @endif
</x-layouts.app>
