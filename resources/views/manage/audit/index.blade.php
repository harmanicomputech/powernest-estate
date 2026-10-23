<x-layouts.app title="Audit log" wide>
    <x-ui.page-header title="Audit log" description="Append-only record of sensitive actions in this estate." />
    <form class="mb-4 flex gap-2"><div class="flex-1"><x-ui.input name="action" :value="$action" placeholder="Filter by action prefix, e.g. power. or visit." /></div><x-ui.button variant="secondary" icon="filter">Filter</x-ui.button></form>
    <x-ui.card :padded="false" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-sm">
                <thead class="bg-canvas text-left text-xs font-semibold uppercase tracking-wide text-muted"><tr><th class="px-5 py-3">When</th><th class="px-3 py-3">Who</th><th class="px-3 py-3">Action</th><th class="px-3 py-3">Details</th><th class="px-5 py-3">IP</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-2.5 text-muted tnum">{{ local_time($log->created_at, 'j M, g:i:sa') }}</td>
                            <td class="px-3 py-2.5">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-3 py-2.5 font-mono text-xs font-semibold">{{ $log->action }}</td>
                            <td class="max-w-sm truncate px-3 py-2.5 font-mono text-xs text-muted" title="{{ json_encode($log->properties) }}">{{ $log->properties ? json_encode($log->properties) : '' }}</td>
                            <td class="px-5 py-2.5 text-xs text-muted">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty icon="history" title="No audit entries" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
