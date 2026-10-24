<x-layouts.app title="Roles & permissions">
    <x-ui.page-header title="Roles & permissions" description="Choose what each role can do in this estate. Give people roles under Members & access.">
        <x-ui.button :href="route('manage.members.index')" variant="secondary" icon="key-round">Members & access</x-ui.button>
    </x-ui.page-header>

    @foreach (['Staff' => $roles->filter(fn ($r) => $r['role']->isStaff()), 'Residents & others' => $roles->reject(fn ($r) => $r['role']->isStaff())] as $heading => $group)
        <p class="eyebrow mb-2 mt-6 first:mt-0">{{ $heading }}</p>
        <x-ui.card :padded="false">
            @foreach ($group as $item)
                <x-ui.row-link :href="route('manage.roles.edit', $item['role'])" icon="{{ $item['role'] === \App\Enums\EstateRole::EstateOwner ? 'crown' : 'shield-check' }}" :class="$loop->first ? '' : 'border-t border-line'">
                    <p class="font-semibold">{{ $item['role']->label() }}</p>
                    <p class="text-sm text-muted">{{ $item['permissions'] }} of {{ $total }} permissions · {{ $item['members'] }} {{ Str::plural('person', $item['members']) }}</p>
                    <x-slot:aside>
                        @if ($item['role'] === \App\Enums\EstateRole::EstateOwner)
                            <x-ui.badge>Full access</x-ui.badge>
                        @elseif ($item['customised'])
                            <x-ui.badge tone="info">Customised</x-ui.badge>
                        @endif
                    </x-slot:aside>
                </x-ui.row-link>
            @endforeach
        </x-ui.card>
    @endforeach
</x-layouts.app>
