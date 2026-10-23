@php use App\Enums\MaintenanceStatus as MS; @endphp
<x-layouts.app :title="$request->reference">
    <x-ui.page-header :title="$request->title" :eyebrow="$request->reference.' · '.($categories[$request->category] ?? humanize($request->category))" :back="route('maintenance.index')">
        <x-ui.status :value="$request->priority" />
        <x-ui.status :value="$request->status" />
    </x-ui.page-header>

    @if ($request->status === MS::Completed && $request->requested_by === auth()->id())
        <x-ui.card class="mb-6 border-[#bfe5cf] bg-success-soft/40">
            <p class="font-bold">Is the problem fixed?</p>
            <p class="mt-1 text-sm text-muted">The team has marked this request as complete. Confirm to close it, or let them know it still needs attention.</p>
            <div class="mt-4 flex flex-wrap gap-2" x-data="{ reopen: false }">
                <form method="POST" action="{{ route('maintenance.confirm', $request) }}">@csrf<input type="hidden" name="resolved" value="1"><x-ui.button icon="check">Yes, it's fixed</x-ui.button></form>
                <x-ui.button type="button" variant="secondary" x-on:click="reopen = true" x-show="!reopen">Still a problem</x-ui.button>
                <form method="POST" action="{{ route('maintenance.confirm', $request) }}" x-show="reopen" x-cloak class="flex w-full gap-2">@csrf<input type="hidden" name="resolved" value="0">
                    <input name="comment" class="control flex-1" placeholder="What's still wrong?" required><x-ui.button variant="danger-soft">Reopen</x-ui.button>
                </form>
            </div>
        </x-ui.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card>
                <p class="whitespace-pre-line text-[0.9375rem] leading-relaxed">{{ $request->description }}</p>
                @if ($request->documents->isNotEmpty())
                    <div class="mt-5 grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($request->documents as $doc)
                            <a href="{{ route('documents.show', $doc) }}" target="_blank" class="group relative block aspect-square overflow-hidden rounded-xl border border-line bg-sunken">
                                @if ($doc->isImage())<img src="{{ route('documents.show', $doc) }}" alt="{{ $doc->original_name }}" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">@else<span class="grid h-full place-items-center"><x-icon name="file-text" class="size-6 text-muted" /></span>@endif
                                @if ($doc->category === 'internal')<span class="absolute left-1.5 top-1.5 rounded bg-ink/80 px-1.5 text-[0.625rem] font-bold text-white">Internal</span>@endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Activity" :padded="false">
                <ol class="relative border-t border-line px-5 py-5 sm:px-6">
                    @foreach ($updates as $update)
                        <li class="relative flex gap-3 pb-5 last:pb-0">
                            @if (! $loop->last)<span class="absolute left-4 top-9 h-[calc(100%-2rem)] w-px bg-line"></span>@endif
                            @if ($update->type === 'comment')
                                <x-ui.avatar :name="$update->user?->name ?? 'System'" size="sm" />
                            @else
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-sunken text-muted"><x-icon :name="match($update->type) { 'status' => 'refresh-cw', 'assignment' => 'user-plus', 'cost' => 'banknote', default => 'info' }" class="size-4" /></span>
                            @endif
                            <div class="min-w-0 flex-1 pt-1">
                                <p class="text-sm"><span class="font-semibold">{{ $update->user?->name ?? 'System' }}</span> <span class="text-faint">· {{ $update->created_at->diffForHumans() }}</span>@if($update->is_internal) <x-ui.badge>Internal</x-ui.badge>@endif</p>
                                <p @class(['mt-0.5 text-sm', 'rounded-xl bg-canvas px-3 py-2 text-ink' => $update->type === 'comment', 'text-muted' => $update->type !== 'comment'])>{{ $update->body }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if ($request->status->isOpen() || $canWork)
                    <form method="POST" action="{{ route('maintenance.comment', $request) }}" enctype="multipart/form-data" class="border-t border-line p-5 sm:p-6" x-data="submitting" x-on:submit="submit">
                        @csrf
                        <x-ui.textarea name="body" rows="2" placeholder="Write an update…" required />
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-4">
                                <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink"><x-icon name="camera" class="size-4" />Photo<input type="file" name="photos[]" accept="image/*" multiple class="sr-only"></label>
                                @if ($canSeeInternal)<label class="inline-flex items-center gap-1.5 text-sm text-muted"><input type="checkbox" name="internal" value="1" class="accent-brand-700">Internal note</label>@endif
                            </div>
                            <x-ui.button size="sm" busy icon="send">Post</x-ui.button>
                        </div>
                    </form>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Details">
                <x-ui.dl :items="[
                    'Property' => $request->property?->identifier,
                    'Location' => $request->location,
                    'Reported by' => $request->requester?->name,
                    'Submitted' => local_time($request->created_at),
                    'Preferred time' => $request->preferred_time,
                    'Assigned to' => $request->assignee?->name ?? ($request->vendor?->name ?? 'Not yet assigned'),
                    'Scheduled' => $request->scheduled_for ? local_time($request->scheduled_for) : null,
                    'Due' => $request->due_at ? local_time($request->due_at) : null,
                    'Labour' => $canSeeInternal && $request->labour_cost ? money($request->labour_cost) : null,
                    'Parts' => $canSeeInternal && $request->parts_cost ? money($request->parts_cost) : null,
                ]" />
            </x-ui.card>

            @if ($canManage && $request->status->isOpen())
                <x-ui.card title="Assign & schedule">
                    <form method="POST" action="{{ route('maintenance.assign', $request) }}" class="space-y-4">
                        @csrf
                        <x-ui.select name="assigned_to" label="Staff" :options="$staff->pluck('name', 'id')->all()" :value="$request->assigned_to" placeholder="—" />
                        <x-ui.select name="vendor_id" label="Or vendor" :options="$vendors->pluck('name', 'id')->all()" :value="$request->vendor_id" placeholder="—" />
                        <x-ui.select name="priority" label="Priority" :options="\App\Enums\Priority::options()" :value="$request->priority" />
                        <x-ui.input name="scheduled_for" type="datetime-local" label="Scheduled visit" optional :value="$request->scheduled_for ? local_time($request->scheduled_for, 'Y-m-d\TH:i') : null" />
                        <x-ui.input name="due_at" type="datetime-local" label="Deadline" optional :value="$request->due_at ? local_time($request->due_at, 'Y-m-d\TH:i') : null" />
                        <x-ui.button class="w-full" icon="user-plus">Save assignment</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            @if ($canWork && $request->status->allowedTransitions())
                <x-ui.card title="Update status">
                    <form method="POST" action="{{ route('maintenance.status', $request) }}" class="space-y-3">
                        @csrf
                        <x-ui.select name="status" :options="collect($request->status->allowedTransitions())->reject(fn ($s) => ! $canManage && in_array($s, [MS::Closed, MS::Cancelled, MS::Assigned], true))->mapWithKeys(fn ($s) => [$s->value => $s === MS::Completed ? 'Completed (ask resident to confirm)' : $s->label()])->all()" />
                        <x-ui.textarea name="note" rows="2" placeholder="Note for the resident (optional)" />
                        <x-ui.button variant="secondary" class="w-full">Update</x-ui.button>
                    </form>
                </x-ui.card>
                <x-ui.card title="Costs">
                    <form method="POST" action="{{ route('maintenance.costs', $request) }}" class="grid grid-cols-2 gap-3">
                        @csrf
                        <x-ui.input name="labour_cost" label="Labour" prefix="₦" :value="\App\Support\Money::toDecimalString($request->labour_cost)" />
                        <x-ui.input name="parts_cost" label="Parts" prefix="₦" :value="\App\Support\Money::toDecimalString($request->parts_cost)" />
                        <x-ui.button variant="secondary" class="col-span-2">Save costs</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
