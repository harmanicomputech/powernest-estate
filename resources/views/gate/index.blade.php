<x-layouts.app title="Gate console">
    <x-ui.page-header title="Gate console" description="Scan a visitor's QR code or enter their 6-digit code. Every check is verified by the server.">
        <x-ui.button :href="route('gate.walk-in')" variant="secondary" icon="user-plus">Walk-in</x-ui.button>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <livewire:gate-console />
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Shift">
                @if ($shift)
                    <p class="text-sm text-muted">On duty since <span class="font-semibold text-ink">{{ local_time($shift->started_at, 'g:ia') }}</span>{{ $shift->gate ? ' at '.$shift->gate->name : '' }}.</p>
                    <form method="POST" action="{{ route('gate.shift') }}" class="mt-4 space-y-3">
                        @csrf
                        <x-ui.textarea name="handover_notes" rows="3" label="Handover notes" placeholder="Anything the next officer should know" optional />
                        <x-ui.button variant="secondary" class="w-full" icon="log-out">End shift</x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('gate.shift') }}" class="space-y-3">
                        @csrf
                        <x-ui.select name="gate_id" label="Gate" :options="$gates->pluck('name', 'id')->all()" placeholder="Select gate" />
                        <x-ui.button class="w-full" icon="log-in">Start shift</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card title="Awaiting host approval" :padded="false">
                @forelse ($awaiting as $visit)
                    <div class="border-t border-line px-5 py-3.5 sm:px-6">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $visit->visitor_name }}</p>
                                <p class="text-xs text-muted">→ {{ $visit->property?->identifier }} · {{ $visit->created_at->diffForHumans(short: true) }}</p>
                            </div>
                            @can('gate.supervise')
                                <x-ui.confirm :action="route('gate.override', $visit)" title="Admit without host approval?" message="This is recorded as a supervisor override in the audit log." confirm="Admit" size="sm" variant="secondary">Override</x-ui.confirm>
                            @else
                                <x-ui.badge tone="warning" dot>Waiting</x-ui.badge>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">No visitors waiting.</p>
                @endforelse
                <div class="border-t border-line px-5 py-3 sm:px-6"><a href="{{ route('gate.index') }}" class="text-sm font-semibold text-brand-700"><x-icon name="refresh-cw" class="mr-1 inline size-3.5" />Refresh</a></div>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
