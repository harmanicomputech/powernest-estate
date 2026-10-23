<x-layouts.app title="New visitor pass">
    <x-ui.page-header title="New visitor pass" :back="route('visitors.index')" description="Your visitor gets a QR code and a 6-digit code. Security verifies it at the gate." />

    <form method="POST" action="{{ route('visitors.store') }}" x-data="submitting" x-on:submit="submit">
        @csrf
        <div class="grid gap-6 lg:grid-cols-3" x-data="{ type: '{{ old('pass_type', 'one_time') }}' }">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Who's visiting?">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="visitor_name" label="Visitor name" required autofocus class="sm:col-span-2" placeholder="e.g. Kola Bankole" />
                    <x-ui.input name="visitor_phone" type="tel" label="Phone" inputmode="tel" optional placeholder="0803 000 0000" />
                    <x-ui.input name="vehicle_plate" label="Vehicle plate" optional placeholder="LND 123 AB" class="uppercase" />
                    <x-ui.input name="group_size" type="number" label="Number of people" value="1" min="1" max="50" />
                    <x-ui.select name="property_id" label="Visiting" :options="$properties->pluck('identifier', 'id')->all()" :value="$properties->first()?->id" required />
                </div>
                <div class="mt-5">
                    <p class="mb-2 text-[0.8125rem] font-semibold text-ink-soft">Visit type</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($categories as $category)
                            <label class="cursor-pointer">
                                <input type="radio" name="category" value="{{ $category }}" class="peer sr-only" @checked(old('category', 'guest') === $category)>
                                <span class="inline-flex h-9 items-center rounded-full border border-line-strong px-3.5 text-sm font-semibold text-ink-soft transition peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-800 peer-focus-visible:shadow-[var(--shadow-focus)]">{{ humanize($category) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('category')<p class="mt-1.5 text-[0.8125rem] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
            </x-ui.card>

            <x-ui.card title="When?">
                <div class="mb-5 grid grid-cols-3 gap-2" role="radiogroup" aria-label="Pass type">
                    @foreach (\App\Enums\PassType::cases() as $type)
                        <label class="cursor-pointer">
                            <input type="radio" name="pass_type" value="{{ $type->value }}" x-model="type" class="peer sr-only">
                            <span class="flex h-full flex-col rounded-xl border border-line-strong p-3 transition peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-focus-visible:shadow-[var(--shadow-focus)]">
                                <span class="text-sm font-bold">{{ $type->label() }}</span>
                                <span class="mt-0.5 text-xs text-muted">{{ match($type) { \App\Enums\PassType::OneTime => 'Single entry', \App\Enums\PassType::MultiEntry => 'Several entries', \App\Enums\PassType::Recurring => 'Set days & hours' } }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="valid_from" type="datetime-local" label="Valid from" :value="$now->format('Y-m-d\TH:i')" required />
                    <x-ui.input name="valid_until" type="datetime-local" label="Valid until" :value="$now->copy()->endOfDay()->format('Y-m-d\TH:i')" required hint="Maximum {{ $maxDays }} days." />
                    <div x-show="type === 'multi_entry'" x-cloak class="sm:col-span-2">
                        <x-ui.input name="max_entries" type="number" label="Maximum entries" min="1" max="100" optional hint="Leave empty for unlimited entries within the window." />
                    </div>
                    <div x-show="type === 'recurring'" x-cloak class="space-y-4 sm:col-span-2">
                        <div>
                            <p class="mb-2 text-[0.8125rem] font-semibold text-ink-soft">Days</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $day)
                                    <label class="cursor-pointer">
                                        <input type="checkbox" name="days[]" value="{{ $i + 1 }}" class="peer sr-only" @checked(in_array($i + 1, old('days', [1, 2, 3, 4, 5])))>
                                        <span class="grid size-10 place-items-center rounded-full border border-line-strong text-sm font-semibold text-ink-soft transition peer-checked:border-brand-600 peer-checked:bg-brand-700 peer-checked:text-white">{{ mb_substr($day, 0, 2) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('days')<p class="mt-1.5 text-[0.8125rem] font-medium text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <x-ui.input name="start_time" type="time" label="From" value="08:00" />
                            <x-ui.input name="end_time" type="time" label="Until" value="17:00" />
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Note for security">
                <x-ui.textarea name="notes" rows="3" placeholder="e.g. Delivering a fridge, allow van in" />
            </x-ui.card>
            <x-ui.alert tone="brand" title="How it works">Share the pass link. Security scans the QR code or enters the 6-digit code; the server checks it's valid right now and for the right home before letting anyone in.</x-ui.alert>
            <x-ui.button class="w-full" size="lg" icon="qr-code" busy>Create pass</x-ui.button>
        </div>
        </div>
    </form>
</x-layouts.app>
