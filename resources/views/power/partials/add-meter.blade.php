<form method="POST" action="{{ route('power.meters.store') }}" class="space-y-4" x-data="submitting" x-on:submit="submit">
    @csrf
    <x-ui.input name="meter_number" label="Meter number" inputmode="numeric" autocomplete="off" required placeholder="e.g. 04721 000 000" hint="Found on the front of your prepaid meter." class="font-mono tnum" />
    <x-ui.select name="property_id" label="Property" :options="$properties->pluck('identifier', 'id')->all()" :value="$properties->first()?->id" required />
    <x-ui.input name="label" label="Name" optional placeholder="e.g. Main house, Boys' quarters" />
    <x-ui.button class="w-full" busy icon="badge-check">Verify and save</x-ui.button>
</form>
