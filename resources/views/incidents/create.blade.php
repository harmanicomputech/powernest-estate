<x-layouts.app title="Report incident">
    <x-ui.page-header title="Report an incident" :back="route('incidents.index')" description="Reports are only visible to you and estate security management." />
    <form method="POST" action="{{ route('incidents.store') }}" enctype="multipart/form-data" class="mx-auto max-w-2xl" x-data="submitting" x-on:submit="submit">
        @csrf
        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.select name="category" label="Type" :options="$categories" placeholder="Choose" required />
                <x-ui.select name="severity" label="Severity" :options="\App\Enums\IncidentSeverity::options()" value="medium" />
                <x-ui.input name="title" label="Summary" required class="sm:col-span-2" />
                <div class="sm:col-span-2"><x-ui.textarea name="description" label="What happened?" rows="5" required /></div>
                <x-ui.input name="location" label="Where?" optional />
                <x-ui.input name="occurred_at" type="datetime-local" label="When?" optional />
                @if ($properties->isNotEmpty())<x-ui.select name="property_id" label="Related property" :options="$properties->pluck('identifier', 'id')->all()" placeholder="None" />@endif
                <div class="sm:col-span-2">
                    <p class="mb-1.5 text-[0.8125rem] font-semibold text-ink-soft">Evidence <span class="font-medium text-faint">(photos or PDF, up to 4)</span></p>
                    <input type="file" name="evidence[]" multiple accept="image/*,application/pdf" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-sunken file:px-3 file:py-2 file:font-semibold">
                </div>
            </div>
            <x-ui.alert tone="danger" class="mt-6" icon="phone">For emergencies in progress, call estate security immediately.</x-ui.alert>
            <x-ui.button class="mt-5 w-full" size="lg" busy icon="send">Submit report</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.app>
