<x-layouts.app title="Visitors">
    <x-ui.page-header title="Visitors" description="Invite guests, deliveries and service providers. Each pass has a QR code and a 6-digit code for the gate.">
        <x-ui.button :href="route('visitors.create')" icon="plus">New pass</x-ui.button>
    </x-ui.page-header>

    @foreach ($awaiting as $visit)
        <a href="{{ route('visitors.walk-ins.show', $visit) }}" class="mb-4 flex items-center gap-4 rounded-[var(--radius-card)] border border-[#f3d9a8] bg-warning-soft p-4">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-warning"><x-icon name="bell-ring" class="size-5" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-bold">{{ $visit->visitor_name }} is waiting at the gate</p>
                <p class="text-sm text-ink-soft">{{ $visit->purpose ?: humanize($visit->category) }} · {{ $visit->created_at->diffForHumans() }}</p>
            </div>
            <span class="text-sm font-bold text-warning">Respond</span>
        </a>
    @endforeach

    <x-ui.tabs :active="$tab" :tabs="['upcoming' => ['Active & upcoming', route('visitors.index')], 'history' => ['History', route('visitors.index', ['tab' => 'history'])]]" />

    <x-ui.card :padded="false">
        @forelse ($passes as $pass)
            @php $status = $pass->effectiveStatus(); @endphp
            <a href="{{ route('visitors.show', $pass) }}" class="group flex items-center gap-4 px-5 py-4 transition hover:bg-canvas sm:px-6 @if(! $loop->first) border-t border-line @endif">
                <x-ui.avatar :name="$pass->visitor_name" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate font-semibold">{{ $pass->visitor_name }}</p>
                        <x-ui.badge>{{ humanize($pass->category) }}</x-ui.badge>
                    </div>
                    <p class="mt-0.5 truncate text-sm text-muted">{{ $pass->property->identifier }} · {{ local_time($pass->valid_from, 'D j M, g:ia') }} – {{ local_time($pass->valid_until, $pass->valid_from->isSameDay($pass->valid_until) ? 'g:ia' : 'D j M, g:ia') }}</p>
                </div>
                <div class="hidden text-right sm:block">
                    <p class="font-mono text-sm font-bold tracking-wider tnum">{{ $status === \App\Enums\PassStatus::Active ? $pass->formattedCode() : '' }}</p>
                    <p class="text-xs text-muted">{{ $pass->pass_type->label() }}</p>
                </div>
                <x-ui.status :value="$status" />
            </a>
        @empty
            <x-ui.empty icon="qr-code" :title="$tab === 'history' ? 'No past passes' : 'No active passes'" description="Passes you create appear here with their status, entries and share link.">
                @if ($tab !== 'history')<x-ui.button :href="route('visitors.create')" icon="plus">Create a pass</x-ui.button>@endif
            </x-ui.empty>
        @endforelse
    </x-ui.card>
    <div class="mt-4">{{ $passes->links() }}</div>

    @if ($recentVisits->isNotEmpty())
        <x-ui.card title="Gate log for your home" class="mt-6" :padded="false">
            @foreach ($recentVisits as $visit)
                <div class="flex items-center gap-3 border-t border-line px-5 py-3 text-sm sm:px-6">
                    <span class="min-w-0 flex-1 truncate font-semibold">{{ $visit->visitor_name }}</span>
                    <span class="text-muted">{{ local_time($visit->checked_in_at, 'D j M, g:ia') }}</span>
                    <x-ui.status :value="$visit->status" />
                </div>
            @endforeach
        </x-ui.card>
    @endif
</x-layouts.app>
