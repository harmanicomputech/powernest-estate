<x-layouts.app title="Onboard estate">
    <x-ui.page-header title="Onboard a new estate" :back="route('platform.dashboard')" description="Creates an isolated estate workspace and invites its owner." />
    <form method="POST" action="{{ route('platform.estates.store') }}" class="mx-auto max-w-2xl">@csrf
        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="name" label="Estate name" required class="sm:col-span-2" />
                <x-ui.input name="code" label="Estate code" required class="uppercase" hint="Residents use this to request access." />
                <x-ui.select name="timezone" label="Timezone" :options="['Africa/Lagos' => 'West Africa (Lagos)', 'UTC' => 'UTC']" value="Africa/Lagos" />
                <x-ui.input name="address" label="Address" class="sm:col-span-2" optional />
                <x-ui.input name="city" label="City" optional /><x-ui.input name="state" label="State" optional />
                <x-ui.input name="contact_email" type="email" label="Office email" optional class="sm:col-span-2" />
            </div>
            <p class="mb-3 mt-8 text-[0.9375rem] font-bold">Estate owner</p>
            <div class="grid gap-5 sm:grid-cols-2"><x-ui.input name="owner_name" label="Name" optional /><x-ui.input name="owner_email" type="email" label="Email" required /></div>
            <x-ui.button class="mt-6 w-full" size="lg">Create estate & send invitation</x-ui.button>
        </x-ui.card>
    </form>
</x-layouts.app>
