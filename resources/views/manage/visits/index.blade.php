<x-layouts.app title="Visitor log" wide>
    <x-ui.page-header title="Visitor log" description="Searchable record of every entry, exit and denial." />
    <form class="mb-4 flex flex-wrap gap-2">
        <div class="min-w-[14rem] flex-1"><x-ui.input name="q" :value="$search" placeholder="Name, phone, plate or unit" /></div>
        <div class="w-44"><x-ui.select name="status" :options="\App\Enums\VisitStatus::options()" :value="$status" placeholder="Any status" /></div>
        <div class="w-44"><x-ui.input name="date" type="date" :value="$date" /></div>
        <x-ui.button variant="secondary" icon="filter">Filter</x-ui.button>
    </form>
    <x-ui.card :padded="false" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[52rem] text-sm">
                <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-wide text-muted"><tr><th class="px-5 py-3">Visitor</th><th class="px-3 py-3">To</th><th class="px-3 py-3">Source</th><th class="px-3 py-3">In</th><th class="px-3 py-3">Out</th><th class="px-3 py-3">Officer</th><th class="px-5 py-3 text-right">Status</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($visits as $visit)
                        <tr class="hover:bg-canvas">
                            <td class="px-5 py-3"><p class="font-semibold">{{ $visit->visitor_name }}</p><p class="text-xs text-muted">{{ humanize($visit->category) }}{{ $visit->vehicle_plate ? ' · '.$visit->vehicle_plate : '' }}</p></td>
                            <td class="px-3 py-3">{{ $visit->property?->identifier ?? '—' }}</td>
                            <td class="px-3 py-3">{{ $visit->source === 'pass' ? 'Pass' : 'Walk-in' }}</td>
                            <td class="px-3 py-3 tnum">{{ $visit->checked_in_at ? local_time($visit->checked_in_at, 'j M, g:ia') : '—' }}</td>
                            <td class="px-3 py-3 tnum">{{ $visit->checked_out_at ? local_time($visit->checked_out_at, 'g:ia') : '—' }}</td>
                            <td class="px-3 py-3 text-muted">{{ $visit->checkedInBy?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right"><x-ui.status :value="$visit->status" />@if($visit->denial_reason)<p class="mt-1 text-xs text-muted">{{ $visit->denial_reason }}</p>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty icon="door-open" title="No visits match" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
    <div class="mt-4">{{ $visits->links() }}</div>
</x-layouts.app>
