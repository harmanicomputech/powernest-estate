<x-layouts.app title="Estate settings">
    <x-ui.page-header title="Estate settings" :description="$estate->name.' · code '.$estate->code" />
    <form method="POST" action="{{ route('manage.settings.update') }}" class="grid gap-6 lg:grid-cols-2">@csrf @method('PUT')
        <x-ui.card title="Estate profile">
            <div class="space-y-4">
                <x-ui.input name="name" label="Name" :value="$estate->name" required />
                <x-ui.input name="address" label="Address" :value="$estate->address" />
                <div class="grid grid-cols-2 gap-3"><x-ui.input name="contact_email" type="email" label="Office email" :value="$estate->contact_email" /><x-ui.input name="contact_phone" label="Office phone" :value="$estate->contact_phone" /></div>
                <x-ui.select name="timezone" label="Timezone" :options="['Africa/Lagos' => 'West Africa (Lagos)', 'UTC' => 'UTC', 'Africa/Accra' => 'Accra', 'Africa/Nairobi' => 'Nairobi']" :value="$estate->timezone" />
            </div>
        </x-ui.card>
        <x-ui.card title="Electricity (PowerNest)">
            <div class="space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <x-ui.input name="service_charge" label="Service charge" prefix="₦" :value="\App\Support\Money::toDecimalString($estate->setting('power.service_charge'))" />
                    <x-ui.input name="min_amount" label="Minimum" prefix="₦" :value="\App\Support\Money::toDecimalString($estate->setting('power.min_amount'))" />
                    <x-ui.input name="max_amount" label="Maximum" prefix="₦" :value="\App\Support\Money::toDecimalString($estate->setting('power.max_amount'))" />
                </div>
                <div>
                    <p class="mb-2 text-[0.8125rem] font-semibold text-ink-soft">Payment methods offered</p>
                    @foreach ($platformGateways as $key => $gateway)
                        <label class="mb-2 flex items-center gap-3 rounded-xl border border-line px-3.5 py-3 text-sm">
                            <input type="checkbox" name="gateways[]" value="{{ $key }}" class="accent-brand-700" @checked(in_array($key, (array) $estate->setting('power.gateways')))>
                            <span class="flex-1 font-semibold">{{ $gateway->label() }}</span>
                            <x-ui.badge :tone="$gateway->isAvailable() ? 'success' : 'neutral'">{{ $gateway->isAvailable() ? 'Configured' : 'Not configured on platform' }}</x-ui.badge>
                        </label>
                    @endforeach
                </div>
            </div>
        </x-ui.card>
        <x-ui.card title="Visitors & privacy">
            <div class="space-y-4">
                <div>
                    <p class="mb-2 text-[0.8125rem] font-semibold text-ink-soft">Pass categories residents can use</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($allCategories as $category)
                            <label class="cursor-pointer"><input type="checkbox" name="pass_categories[]" value="{{ $category }}" class="peer sr-only" @checked(in_array($category, (array) $estate->setting('visitors.pass_categories')))><span class="inline-flex h-9 items-center rounded-full border border-line-strong px-3.5 text-sm font-semibold text-ink-soft peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-800">{{ humanize($category) }}</span></label>
                        @endforeach
                    </div>
                </div>
                <x-ui.input name="max_pass_days" type="number" label="Longest pass (days)" :value="$estate->setting('visitors.max_pass_days')" />
                <x-ui.checkbox name="require_walk_in_approval" label="Walk-in visitors need host approval" :checked="(bool) $estate->setting('visitors.require_walk_in_approval')" />
                <x-ui.checkbox name="directory_visible" label="Residents can see a resident directory" description="Off by default for privacy." :checked="(bool) $estate->setting('residents.directory_visible')" />
            </div>
        </x-ui.card>
        <x-ui.card title="Yearly estate levy" description="Estate management & security fee billed once a year.">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input name="levy_amount" label="Amount per home" prefix="₦" :value="\App\Support\Money::toDecimalString($estate->setting('levy.amount'))" />
                    <x-ui.input name="levy_grace_days" type="number" label="Days after due date" :value="$estate->setting('levy.grace_days')" hint="Before access is paused" />
                </div>
                <x-ui.checkbox name="levy_lockout_enabled" label="Pause app access for residents who haven't paid" description="They can still pay online or upload a bank receipt; access returns once paid or the receipt is approved. Staff are never locked." :checked="(bool) $estate->setting('levy.lockout_enabled')" />
                <p class="text-sm text-muted">Bill everyone for the year from <a href="{{ route('manage.billing.index') }}" class="link">Billing</a> → “Estate management & security levy” → Run.</p>
            </div>
        </x-ui.card>
        <div class="flex items-end lg:col-span-2"><x-ui.button size="lg" icon="check">Save settings</x-ui.button></div>
    </form>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Gates" :padded="false">
            @foreach ($gates as $gate)<div class="border-t border-line px-5 py-3 text-sm font-semibold sm:px-6">{{ $gate->name }}</div>@endforeach
            <form method="POST" action="{{ route('manage.settings.gates.store') }}" class="flex gap-2 border-t border-line p-5 sm:p-6">@csrf<input name="name" class="control flex-1" placeholder="Gate name" required><x-ui.button variant="secondary">Add</x-ui.button></form>
        </x-ui.card>
        <x-ui.card title="Emergency contacts" :padded="false">
            @foreach ($contacts as $contact)<div class="flex justify-between border-t border-line px-5 py-3 text-sm sm:px-6"><span class="font-semibold">{{ $contact->name }} <span class="font-normal text-muted">{{ $contact->role }}</span></span><a href="tel:{{ $contact->phone }}" class="link">{{ $contact->phone }}</a></div>@endforeach
            <form method="POST" action="{{ route('manage.settings.contacts.store') }}" class="grid grid-cols-3 gap-2 border-t border-line p-5 sm:p-6">@csrf<input name="name" class="control" placeholder="Name" required><input name="phone" class="control" placeholder="Phone" required><x-ui.button variant="secondary">Add</x-ui.button></form>
        </x-ui.card>
    </div>
</x-layouts.app>
