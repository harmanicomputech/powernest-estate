<x-layouts.app :title="$incident->reference">
    <x-ui.page-header :title="$incident->title" :eyebrow="$incident->reference.' · '.($categories[$incident->category] ?? humanize($incident->category))" :back="route('incidents.index')">
        <x-ui.status :value="$incident->severity" /><x-ui.status :value="$incident->status" />
    </x-ui.page-header>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card>
                <p class="whitespace-pre-line leading-relaxed">{{ $incident->description }}</p>
                @if ($incident->documents->isNotEmpty())
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($incident->documents as $doc)
                            <a href="{{ route('documents.show', $doc) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm font-semibold hover:bg-sunken"><x-icon :name="$doc->isImage() ? 'image' : 'file-text'" class="size-4" />{{ \Illuminate\Support\Str::limit($doc->original_name, 24) }}</a>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
            <x-ui.card title="Investigation log" :padded="false">
                @foreach ($updates as $update)
                    <div class="border-t border-line px-5 py-3.5 sm:px-6">
                        <p class="text-sm"><span class="font-semibold">{{ $update->user?->name ?? 'System' }}</span> <span class="text-faint">· {{ $update->created_at->diffForHumans() }}</span></p>
                        <p class="mt-0.5 text-sm text-ink-soft">{{ $update->body }}</p>
                    </div>
                @endforeach
                @if ($canNote)
                    <form method="POST" action="{{ route('incidents.note', $incident) }}" class="flex gap-2 border-t border-line p-5 sm:p-6">@csrf
                        <input name="body" class="control flex-1" placeholder="Add investigation note" required>
                        <x-ui.button>Add</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        </div>
        <div class="space-y-6">
            <x-ui.card title="Details">
                <x-ui.dl :items="['Reported by' => $incident->reporter?->name, 'Occurred' => local_time($incident->occurred_at), 'Location' => $incident->location, 'Property' => $incident->property?->identifier, 'Assigned to' => $incident->assignee?->name ?? 'Unassigned', 'Resolution' => $incident->resolution]" />
            </x-ui.card>
            @if ($canManage)
                <x-ui.card title="Manage">
                    <form method="POST" action="{{ route('incidents.assign', $incident) }}" class="space-y-3">@csrf
                        <x-ui.select name="assigned_to" label="Assign to" :options="$staff->pluck('name', 'id')->all()" :value="$incident->assigned_to" placeholder="Choose staff" />
                        <x-ui.button variant="secondary" class="w-full">Assign</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('incidents.status', $incident) }}" class="mt-5 space-y-3 border-t border-line pt-5">@csrf
                        <x-ui.select name="status" label="Status" :options="\App\Enums\IncidentStatus::options()" :value="$incident->status" />
                        <x-ui.textarea name="note" rows="2" placeholder="Resolution / note (required when resolving)" />
                        <x-ui.button class="w-full">Update status</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
