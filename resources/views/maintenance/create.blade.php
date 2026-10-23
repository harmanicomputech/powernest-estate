<x-layouts.app title="New maintenance request">
    <x-ui.page-header title="Report a problem" :back="route('maintenance.index')" description="Describe the issue and add photos. The facility team will triage and keep you updated." />
    <form method="POST" action="{{ route('maintenance.store') }}" enctype="multipart/form-data" class="mx-auto max-w-2xl" x-data="submitting" x-on:submit="submit">
        @csrf
        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.select name="category" label="Category" :options="$categories" placeholder="Choose a category" required />
                <x-ui.select name="property_id" label="Property" :options="$properties->pluck('identifier', 'id')->all()" :value="$properties->first()?->id" placeholder="Common area" />
                <x-ui.input name="title" label="What's wrong?" required class="sm:col-span-2" placeholder="e.g. Kitchen sink leaking" />
                <div class="sm:col-span-2"><x-ui.textarea name="description" label="Details" rows="5" required placeholder="When did it start? What have you noticed?" /></div>
                <x-ui.input name="location" label="Exact location" optional placeholder="e.g. Upstairs bathroom" />
                <x-ui.select name="priority" label="How urgent?" :options="\App\Enums\Priority::options()" value="normal" />
                <x-ui.input name="preferred_time" label="Preferred visit time" optional placeholder="e.g. Weekdays after 4pm" class="sm:col-span-2" />
                <div class="sm:col-span-2" x-data="{ files: [] }">
                    <p class="mb-1.5 text-[0.8125rem] font-semibold text-ink-soft">Photos <span class="font-medium text-faint">(up to 4)</span></p>
                    <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed border-line-strong px-4 py-6 text-center text-sm text-muted transition hover:border-brand-500">
                        <x-icon name="camera" class="size-6" />
                        <span x-text="files.length ? files.length + ' photo(s) selected' : 'Tap to take or choose photos'"></span>
                        <input type="file" name="photos[]" accept="image/*" multiple class="sr-only" x-on:change="files = [...$event.target.files]">
                    </label>
                    @error('photos.*')<p class="mt-1 text-[0.8125rem] text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
            <x-ui.button class="mt-6 w-full" size="lg" busy icon="send">Submit request</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.app>
