<x-layouts.app title="Buy electricity">
    <x-ui.page-header title="Buy electricity" :back="route('power.index')" />

    @if (empty($gateways))
        <x-ui.alert tone="warning" title="Payments are not available" class="mx-auto max-w-lg">Online payment hasn't been enabled for this estate yet. Please contact the estate office.</x-ui.alert>
    @else
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="space-y-6 lg:col-span-3">
                <x-ui.card title="1. Meter">
                    @if ($selected)
                        @include('power.partials.meter-summary', ['meter' => $selected])
                        <a href="{{ route('power.buy') }}" class="mt-3 inline-flex text-sm font-semibold text-brand-700 hover:underline">Use a different meter</a>
                    @else
                        <form method="POST" action="{{ route('power.meters.store') }}" class="space-y-3">@csrf
                            <x-ui.input name="meter_number" label="Meter number" inputmode="numeric" autocomplete="off" required placeholder="e.g. 0472 1000 000" hint="Printed on the front of the prepaid meter." class="font-mono text-lg tnum" autofocus />
                            <x-ui.button class="w-full" icon="search">Check meter</x-ui.button>
                        </form>
                        @if ($meters->isNotEmpty())
                            <p class="eyebrow mb-2 mt-6">Or choose a saved meter</p>
                            <div class="space-y-2">
                                @foreach ($meters as $meter)
                                    <a href="{{ route('power.buy', ['meter' => $meter->id]) }}" data-ripple class="flex items-center gap-3 rounded-xl border border-line-strong px-4 py-3 transition hover:border-brand-500">
                                        <x-icon name="zap" class="size-[1.125rem] shrink-0 text-gold-500" />
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-mono text-sm font-bold tnum">{{ $meter->meter_number }}</span>
                                            <span class="block truncate text-xs text-muted">{{ $meter->vendor_customer_name ?? $meter->label }}{{ ($meter->vendor_address ?? $meter->property?->identifier) ? ' · '.($meter->vendor_address ?? $meter->property?->identifier) : '' }}</span>
                                        </span>
                                        <x-icon name="chevron-right" class="btn-icon size-4 text-faint" />
                                        <x-icon name="loader" class="btn-spinner size-4 animate-spin text-brand-600" />
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </x-ui.card>

                @if ($selected)
                    <form id="buy-form" method="POST" action="{{ route('power.purchases.store') }}" class="space-y-6"
                          x-data="{ amount: '{{ old('amount', '') }}' }">
                        @csrf
                        <input type="hidden" name="meter_id" value="{{ $selected->id }}">
                        @error('meter_id')<x-ui.alert tone="danger">{{ $message }}</x-ui.alert>@enderror

                        <x-ui.card title="2. Amount">
                            <x-ui.input name="amount" label="Electricity value" prefix="₦" inputmode="decimal" x-model="amount" autocomplete="off" required class="text-lg font-bold tnum" :hint="'Between '.money($min, false).' and '.money($max, false).'.'" />
                            <div class="mt-3 grid grid-cols-4 gap-2">
                                @foreach ([2000, 5000, 10000, 20000] as $preset)
                                    @if ($preset * 100 >= $min && $preset * 100 <= $max)
                                        <button type="button" x-on:click="amount = '{{ $preset }}'; $dispatch('amount-changed', amount)" class="h-10 rounded-xl border border-line-strong text-sm font-semibold transition hover:border-brand-500" :class="amount == '{{ $preset }}' && 'border-brand-600 bg-brand-50 text-brand-800'">₦{{ number_format($preset) }}</button>
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
                    </form>
                @endif
            </div>

            @if ($selected)
                <div class="lg:col-span-2" x-data="{ amount: '{{ old('amount', '') }}', charge: {{ $serviceCharge }} }" x-on:input.window="if ($event.target.name === 'amount') amount = $event.target.value" x-on:amount-changed.window="amount = $event.detail">
                    <div class="card card-pad lg:sticky lg:top-24">
                        <h2 class="text-[0.9375rem] font-bold">Summary</h2>
                        @php $toKobo = "(() => { const v = String(amount).replace(/[^0-9.]/g, ''); if (!v) return 0; const [w, f = ''] = v.split('.'); return parseInt(w || '0', 10) * 100 + parseInt((f + '00').slice(0, 2), 10); })()"; @endphp
                        <dl class="mt-4 space-y-3 text-sm" x-data="{ fmt: (k) => '₦' + (k / 100).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }">
                            <div class="flex justify-between gap-3"><dt class="text-muted">Meter</dt><dd class="truncate font-mono font-semibold tnum">{{ $selected->meter_number }}</dd></div>
                            <div class="flex justify-between"><dt class="text-muted">Electricity</dt><dd class="font-semibold tnum" x-text="fmt({{ $toKobo }})">₦0.00</dd></div>
                            <div class="flex justify-between"><dt class="text-muted">Service charge</dt><dd class="font-semibold tnum">{{ money($serviceCharge) }}</dd></div>
                            <div class="flex justify-between border-t border-line pt-3 text-base"><dt class="font-bold">Total to pay</dt><dd class="font-extrabold tnum" x-text="fmt({{ $toKobo }} + charge)">{{ money($serviceCharge) }}</dd></div>
                        </dl>
                        <p class="mt-3 text-xs text-muted">The final amount is confirmed by the server before payment. Units are set by the vending service.</p>
                        <x-ui.button form="buy-form" class="mt-5 w-full" size="lg" icon="lock">Continue to payment</x-ui.button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-layouts.app>
