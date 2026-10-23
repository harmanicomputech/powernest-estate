<x-layouts.app :title="$estate->name">
    <x-ui.page-header :title="$estate->name" :eyebrow="'Code '.$estate->code" :back="route('platform.dashboard')">
        <x-ui.badge :tone="$estate->status === 'active' ? 'success' : 'danger'" dot>{{ ucfirst($estate->status) }}</x-ui.badge>
    </x-ui.page-header>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Overview">
            <x-ui.dl :items="['Address' => $estate->address, 'Timezone' => $estate->timezone, 'Properties' => $estate->properties_count, 'Active members' => $estate->active_memberships_count, 'Created' => $estate->created_at->format('j M Y'), 'Demo data' => $estate->is_demo ? 'Yes' : 'No']" />
            <form method="POST" action="{{ route('platform.estates.status', $estate) }}" class="mt-5">@csrf @method('PUT')
                @if ($estate->status === 'active')
                    <x-ui.button name="status" value="suspended" variant="danger-soft" class="w-full">Suspend estate</x-ui.button>
                @else
                    <x-ui.button name="status" value="active" class="w-full">Reactivate estate</x-ui.button>
                @endif
            </form>
        </x-ui.card>
        <x-ui.card title="Owners & managers" :padded="false">
            @foreach ($owners as $membership)
                <div class="flex justify-between border-t border-line px-5 py-3 text-sm sm:px-6"><span class="font-semibold">{{ $membership->user->name }}</span><span class="text-muted">{{ $membership->role->label() }}</span></div>
            @endforeach
            <form method="POST" action="{{ route('platform.estates.invite', $estate) }}" class="grid gap-2 border-t border-line p-5 sm:grid-cols-3 sm:p-6">@csrf
                <input name="email" type="email" class="control sm:col-span-2" placeholder="Email" required>
                <select name="role" class="control"><option value="estate_owner">Owner</option><option value="estate_manager">Manager</option></select>
                <x-ui.button variant="secondary" class="sm:col-span-3">Send invitation</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
