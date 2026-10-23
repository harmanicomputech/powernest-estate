<x-layouts.app title="Residents" wide>
    <x-ui.page-header title="Residents" description="People linked to properties. Visible to authorised estate staff only." />
    <form class="mb-4 flex flex-wrap gap-2">
        <div class="min-w-[14rem] flex-1"><x-ui.input name="q" :value="$search" placeholder="Search name, email, phone or unit" /></div>
        <div class="w-48"><x-ui.select name="relationship" :options="\App\Enums\OccupancyRelationship::options()" :value="$relationship" placeholder="All types" onchange="this.form.submit()" /></div>
        <x-ui.button variant="secondary" icon="search">Search</x-ui.button>
    </form>
    <x-ui.card :padded="false" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-wide text-muted"><tr><th class="px-5 py-3">Name</th><th class="px-3 py-3">Unit</th><th class="px-3 py-3">Relationship</th><th class="px-3 py-3">Contact</th><th class="px-5 py-3">Since</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($occupancies as $occupancy)
                        <tr class="hover:bg-canvas">
                            <td class="px-5 py-3"><div class="flex items-center gap-3"><x-ui.avatar :user="$occupancy->user" size="sm" /><span class="font-semibold">{{ $occupancy->user->name }}</span></div></td>
                            <td class="px-3 py-3"><a href="{{ route('manage.properties.show', $occupancy->property) }}" class="font-semibold text-brand-700 hover:underline">{{ $occupancy->property->identifier }}</a></td>
                            <td class="px-3 py-3"><x-ui.badge :tone="$occupancy->relationship->tone()">{{ $occupancy->relationship->label() }}</x-ui.badge></td>
                            <td class="px-3 py-3 text-muted">{{ $occupancy->user->email }}<br>{{ $occupancy->user->phone }}</td>
                            <td class="px-5 py-3 text-muted">{{ $occupancy->starts_on?->format('M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty icon="users" title="No residents found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
    <div class="mt-4">{{ $occupancies->links() }}</div>
</x-layouts.app>
