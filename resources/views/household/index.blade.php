<x-layouts.app title="My home">
    <x-ui.page-header title="My home" description="Your properties, the people linked to them, and move requests." />
    <div class="space-y-6">
        @forelse ($occupancies as $occupancy)
            @php $property = $occupancy->property; $isPrimary = $occupancy->is_primary && in_array($occupancy->relationship, [\App\Enums\OccupancyRelationship::Owner, \App\Enums\OccupancyRelationship::Tenant]); @endphp
            <x-ui.card :padded="false">
                <div class="flex flex-wrap items-center gap-4 p-5 sm:p-6">
                    <span class="grid size-12 place-items-center rounded-2xl bg-brand-800 text-gold-200"><x-icon name="house" class="size-6" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-extrabold">{{ $property->identifier }}</p>
                        <p class="text-sm text-muted">{{ $property->type->label() }}{{ $property->building ? ' · '.$property->building->name : '' }}{{ $property->bedrooms ? ' · '.$property->bedrooms.' bed' : '' }}</p>
                    </div>
                    <x-ui.badge :tone="$occupancy->relationship->tone()">You: {{ $occupancy->relationship->label() }}</x-ui.badge>
                </div>
                <div class="border-t border-line">
                    <p class="eyebrow px-5 pb-1 pt-4 sm:px-6">People</p>
                    @foreach ($property->occupancies as $member)
                        <div class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <x-ui.avatar :user="$member->user" size="sm" />
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $member->user->name }}{{ $member->user_id === auth()->id() ? ' (you)' : '' }}</p><p class="text-xs text-muted">{{ $member->relationship->label() }} · since {{ $member->starts_on?->format('M Y') }}</p></div>
                            @if ($isPrimary && $member->relationship === \App\Enums\OccupancyRelationship::HouseholdMember && $member->primary_occupancy_id === $occupancy->id)
                                <x-ui.confirm :action="route('household.remove', $member)" method="DELETE" title="Remove {{ $member->user->name }}?" message="They will lose access to visitors, power and other services for this home." confirm="Remove" confirm-variant="danger" size="sm" variant="ghost">Remove</x-ui.confirm>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($property->documents->isNotEmpty())
                    <div class="border-t border-line">
                        <p class="eyebrow px-5 pb-1 pt-4 sm:px-6">Documents</p>
                        @foreach ($property->documents as $doc)
                            <a href="{{ route('documents.show', $doc) }}" target="_blank" class="flex items-center gap-3 px-5 py-2.5 text-sm font-semibold hover:bg-canvas sm:px-6"><x-icon name="file-text" class="size-4 text-muted" />{{ $doc->original_name }}</a>
                        @endforeach
                    </div>
                @endif
                @if ($isPrimary)
                    <div class="border-t border-line p-5 sm:p-6" x-data="{ open: false }">
                        <button type="button" x-on:click="open = !open" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700"><x-icon name="user-plus" class="size-4" />Add household member</button>
                        <form method="POST" action="{{ route('household.invite') }}" x-show="open" x-cloak class="mt-4 grid gap-3 sm:grid-cols-3">@csrf
                            <input type="hidden" name="property_id" value="{{ $property->id }}">
                            <x-ui.input name="name" placeholder="Full name" required />
                            <x-ui.input name="email" type="email" placeholder="Email" required />
                            <x-ui.button>Send invite</x-ui.button>
                        </form>
                    </div>
                @endif
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.empty icon="house" title="No home linked" description="Your account isn't linked to a property in this estate yet. Ask the estate office to add you." /></x-ui.card>
        @endforelse

        @if ($occupancies->isNotEmpty())
            <x-ui.card title="Moving in or out?" description="Let management know so they can plan access and handover.">
                <form method="POST" action="{{ route('household.move') }}" class="grid gap-4 sm:grid-cols-4">@csrf
                    <x-ui.select name="property_id" :options="$occupancies->mapWithKeys(fn ($o) => [$o->property->id => $o->property->identifier])->all()" />
                    <x-ui.select name="type" :options="['move_in' => 'Moving in', 'move_out' => 'Moving out']" />
                    <x-ui.input name="scheduled_for" type="date" required />
                    <x-ui.button variant="secondary">Send request</x-ui.button>
                </form>
                @foreach ($moveRequests as $move)
                    <div class="mt-3 flex items-center justify-between rounded-xl bg-canvas px-4 py-2.5 text-sm"><span>{{ $move->type === 'move_in' ? 'Move in' : 'Move out' }} · {{ $move->property->identifier }} · {{ $move->scheduled_for->format('j M Y') }}</span><x-ui.badge :tone="['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'completed' => 'neutral'][$move->status]">{{ ucfirst($move->status) }}</x-ui.badge></div>
                @endforeach
            </x-ui.card>
        @endif
    </div>
</x-layouts.app>
