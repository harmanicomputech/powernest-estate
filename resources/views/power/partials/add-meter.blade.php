<form method="POST" action="{{ route('power.meters.store') }}" class="space-y-4">
    @csrf
    <x-ui.input name="meter_number" label="Meter number" inputmode="numeric" autocomplete="off" required placeholder="e.g. 0472 1000 000" hint="Printed on the front of the prepaid meter. We check it with the meter service and show you the details." class="font-mono tnum" />
    <x-ui.button class="w-full" icon="search">Check meter</x-ui.button>
</form>
