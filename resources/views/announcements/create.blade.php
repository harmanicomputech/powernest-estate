<x-layouts.app title="Publish announcement">
    <x-ui.page-header title="Publish announcement" :back="route('announcements.index')" />
    <form method="POST" action="{{ route('announcements.store') }}" class="mx-auto max-w-2xl" x-data="{ audience: 'estate' }" x-on:submit="$el.querySelector('button[type=submit]').disabled = true">
        @csrf
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.input name="title" label="Title" required />
                <x-ui.textarea name="body" label="Message" rows="8" required />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.select name="category" label="Category" :options="\App\Enums\AnnouncementCategory::options()" value="general" />
                    <x-ui.select name="audience" label="Audience" :options="['estate' => 'Whole estate', 'building' => 'One building / street']" x-model="audience" />
                    <div x-show="audience === 'building'" x-cloak><x-ui.select name="building_id" label="Building" :options="$buildings->pluck('name', 'id')->all()" placeholder="Choose" /></div>
                    <x-ui.input name="expires_at" type="date" label="Hide after" optional />
                </div>
                <x-ui.checkbox name="is_pinned" label="Pin to the top" />
                <x-ui.checkbox name="notify" label="Notify residents now" description="Emergency announcements always notify everyone." />
            </div>
            <x-ui.button class="mt-6 w-full" size="lg" icon="megaphone">Publish</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.app>
