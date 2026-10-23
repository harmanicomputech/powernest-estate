<x-layouts.app title="Platform console" wide>
    <x-ui.page-header title="Platform console" description="Estates, integration health and transaction processing across PowerNest Estate.">
        <x-ui.button :href="route('platform.estates.create')" icon="plus">Onboard estate</x-ui.button>
    </x-ui.page-header>
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Estates" :value="$estates->count()" icon="building-2" :hint="$estates->where('status', 'active')->count().' active'" />
        <x-ui.stat label="Users" :value="number_format($users)" icon="users" tone="info" />
        <x-ui.stat label="Tokens issued (24h)" :value="$power['issued24h']" icon="zap" tone="gold" />
        <x-ui.stat label="Needs reconciliation" :value="$power['attention']" icon="triangle-alert" tone="danger" :hint="$power['pending'].' awaiting payment (24h)'" />
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <x-ui.card title="Integrations" :padded="false" class="lg:col-span-2">
            @foreach ($integrations as $integration)
                <div class="flex items-start gap-3 border-t border-line px-5 py-3.5 sm:px-6">
                    <span @class(['mt-1 size-2.5 shrink-0 rounded-full', 'bg-success' => $integration['ok'], 'bg-warning' => ! $integration['ok']])></span>
                    <div><p class="text-sm font-semibold">{{ $integration['name'] }}</p><p class="text-xs text-muted">{{ $integration['detail'] }}</p></div>
                </div>
            @endforeach
            <div class="border-t border-line px-5 py-3.5 text-xs text-muted sm:px-6">Webhooks (24h): {{ $power['webhooks24h'] }} · rejected Paystack signatures: {{ $power['badSignatures'] }}</div>
        </x-ui.card>
        <x-ui.card title="Estates" :padded="false" class="lg:col-span-3">
            @foreach ($estates as $estate)
                <a href="{{ route('platform.estates.show', $estate) }}" class="flex items-center gap-3 border-t border-line px-5 py-3.5 hover:bg-canvas sm:px-6">
                    <span class="grid size-9 place-items-center rounded-lg bg-brand-800 text-sm font-extrabold text-gold-200">{{ mb_substr($estate->name, 0, 1) }}</span>
                    <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $estate->name }} @if($estate->is_demo)<x-ui.badge tone="gold">Demo</x-ui.badge>@endif</p><p class="text-xs text-muted">{{ $estate->code }} · {{ $estate->properties_count }} properties · {{ $estate->active_memberships_count }} active members</p></div>
                    <x-ui.badge :tone="$estate->status === 'active' ? 'success' : 'danger'" dot>{{ ucfirst($estate->status) }}</x-ui.badge>
                </a>
            @endforeach
        </x-ui.card>
    </div>
    <x-ui.card title="Platform audit" class="mt-6" :padded="false">
        @forelse ($audit as $log)
            <div class="flex justify-between border-t border-line px-5 py-3 text-sm sm:px-6"><span><span class="font-semibold">{{ $log->user?->name ?? 'System' }}</span> · <span class="font-mono text-xs">{{ $log->action }}</span></span><span class="text-muted">{{ $log->created_at->diffForHumans() }}</span></div>
        @empty
            <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">No platform events.</p>
        @endforelse
    </x-ui.card>
</x-layouts.app>
