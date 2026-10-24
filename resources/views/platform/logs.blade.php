<x-layouts.app title="System log">
    <x-ui.page-header title="System log" :back="route('platform.dashboard')" description="The 50 most recent warnings and errors. Share an entry with your developer when something fails." />

    @if (! $file || ! $entries)
        <x-ui.empty icon="circle-check" title="No errors recorded" description="Nothing has been logged recently." />
    @else
        <p class="mb-3 text-sm text-muted">From <span class="font-mono">{{ $file }}</span> (times in UTC)</p>
        <div class="space-y-3">
            @foreach ($entries as $entry)
                <details class="card group overflow-hidden">
                    <summary class="flex cursor-pointer list-none items-start gap-3 px-5 py-4">
                        <x-ui.badge :tone="in_array($entry['level'], ['error', 'critical', 'alert', 'emergency']) ? 'danger' : ($entry['level'] === 'warning' ? 'warning' : 'neutral')">{{ ucfirst($entry['level']) }}</x-ui.badge>
                        <div class="min-w-0 flex-1">
                            <p class="break-words text-sm font-semibold">{{ $entry['message'] }}</p>
                            <p class="mt-0.5 text-xs text-muted tnum">{{ $entry['time'] }}</p>
                        </div>
                        @if ($entry['detail'])<x-icon name="chevron-down" class="mt-1 size-4 shrink-0 text-faint transition group-open:rotate-180" />@endif
                    </summary>
                    @if ($entry['detail'])
                        <pre class="max-h-80 overflow-auto border-t border-line bg-sunken px-5 py-3 text-[0.75rem] leading-relaxed">{{ $entry['detail'] }}</pre>
                    @endif
                </details>
            @endforeach
        </div>
    @endif
</x-layouts.app>
