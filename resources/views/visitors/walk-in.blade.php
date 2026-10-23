<x-layouts.app title="Visitor at the gate">
    <x-ui.page-header title="Visitor at the gate" :back="route('visitors.index')" />
    <div class="mx-auto max-w-lg">
        <x-ui.card>
            <div class="flex items-center gap-4">
                <x-ui.avatar :name="$visit->visitor_name" size="lg" />
                <div>
                    <p class="text-xl font-extrabold">{{ $visit->visitor_name }}</p>
                    <p class="text-sm text-muted">Arrived {{ $visit->created_at->diffForHumans() }}{{ $visit->gate ? ' · '.$visit->gate->name : '' }}</p>
                </div>
            </div>
            <x-ui.dl class="mt-6" :items="['For' => $visit->property->identifier, 'Visit type' => humanize($visit->category), 'Purpose' => $visit->purpose, 'Phone' => $visit->visitor_phone, 'Vehicle' => $visit->vehicle_plate, 'People' => $visit->group_size]" />

            @if ($visit->status === \App\Enums\VisitStatus::AwaitingHost)
                <form method="POST" action="{{ route('visitors.walk-ins.respond', $visit) }}" class="mt-6 grid grid-cols-2 gap-3">
                    @csrf
                    <x-ui.button name="decision" value="decline" variant="danger-soft" size="lg" icon="x">Decline</x-ui.button>
                    <x-ui.button name="decision" value="approve" size="lg" icon="check">Let them in</x-ui.button>
                </form>
            @else
                <x-ui.alert class="mt-6" :tone="$visit->status === \App\Enums\VisitStatus::Inside ? 'success' : 'info'">This visit is already {{ strtolower($visit->status->label()) }}.</x-ui.alert>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
