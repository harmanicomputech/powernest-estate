<x-layouts.app title="Register walk-in">
    <x-ui.page-header title="Register walk-in" :back="route('gate.index')" :description="$needsApproval ? 'The resident will be asked to approve before the visitor is admitted.' : 'The visitor will be checked in immediately.'" />
    <form method="POST" action="{{ route('gate.walk-in.store') }}" class="mx-auto max-w-xl" x-data="submitting" x-on:submit="submit">
        @csrf
        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="visitor_name" label="Visitor name" required autofocus class="sm:col-span-2" />
                <x-ui.input name="visitor_phone" type="tel" label="Phone" optional />
                <x-ui.select name="property_id" label="Visiting" :options="$properties->pluck('identifier', 'id')->all()" placeholder="Select property" required />
                <x-ui.select name="category" label="Visit type" :options="collect($categories)->mapWithKeys(fn ($c) => [$c => humanize($c)])->all()" value="guest" />
                <x-ui.input name="group_size" type="number" label="People" value="1" min="1" />
                <x-ui.input name="vehicle_plate" label="Vehicle plate" optional class="uppercase" />
                <x-ui.input name="purpose" label="Purpose" optional />
            </div>
            <x-ui.button class="mt-6 w-full" size="lg" busy icon="user-plus">{{ $needsApproval ? 'Request host approval' : 'Check in visitor' }}</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.app>
