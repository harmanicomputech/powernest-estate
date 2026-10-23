<x-layouts.app title="Add property">
    <x-ui.page-header title="Add property" :back="route('manage.properties.index')" />
    <form method="POST" action="{{ route('manage.properties.store') }}" class="mx-auto max-w-xl">@csrf
        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="identifier" label="Unit identifier" required class="uppercase" placeholder="A-101" hint="Unique within the estate." />
                <x-ui.select name="building_id" label="Building / street" :options="$buildings->pluck('name', 'id')->all()" placeholder="None" />
                <x-ui.select name="type" label="Type" :options="\App\Enums\PropertyType::options()" value="house" />
                <x-ui.input name="bedrooms" type="number" label="Bedrooms" optional />
                <x-ui.input name="floor" label="Floor" optional />
                <x-ui.input name="address_line" label="Address line" optional />
            </div>
            <x-ui.button class="mt-6 w-full">Add property</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.app>
