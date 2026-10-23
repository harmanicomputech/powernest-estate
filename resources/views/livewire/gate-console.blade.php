<div class="space-y-4">
    <div class="card overflow-hidden" x-data="passScanner" x-on:pass-scanned.window="$wire.scanned($event.detail.value)">
        <div class="relative aspect-[4/3] bg-brand-950 sm:aspect-video" x-show="active" x-cloak>
            <video x-ref="video" class="h-full w-full object-cover" playsinline muted></video>
            <button type="button" x-on:click="stop()" class="absolute right-3 top-3 grid size-10 place-items-center rounded-full bg-black/50 text-white" aria-label="Stop camera"><x-icon name="x" class="size-5" /></button>
        </div>
        <div class="p-5 sm:p-6">
            <form wire:submit="check" class="flex gap-2">
                <label for="gate-code" class="sr-only">Access code</label>
                <input id="gate-code" wire:model="code" type="text" inputmode="numeric" autocomplete="off" placeholder="Enter 6-digit code" class="control h-13 flex-1 text-center font-mono text-xl font-bold tracking-[0.3em] tnum placeholder:font-sans placeholder:text-base placeholder:font-medium placeholder:tracking-normal">
                <x-ui.button size="lg" class="h-13" wire:loading.attr="disabled" wire:target="check,scanned">
                    <x-icon name="loader" class="size-5 animate-spin" wire:loading wire:target="check,scanned" />
                    <span wire:loading.remove wire:target="check,scanned">Verify</span>
                </x-ui.button>
            </form>
            <button type="button" x-show="!active" x-on:click="start()" class="mt-3 flex h-12 w-full items-center justify-center gap-2 rounded-[var(--radius-control)] border border-dashed border-line-strong text-sm font-semibold text-ink-soft transition hover:border-brand-500 hover:text-brand-700">
                <x-icon name="camera" class="size-5" /> Scan QR code
            </button>
            <p x-show="error" x-text="error" x-cloak class="mt-3 text-sm font-medium text-danger"></p>
        </div>
    </div>

    @if ($admitted)
        <div class="flex items-center gap-3 rounded-[var(--radius-card)] border border-[#bfe5cf] bg-success-soft p-4 animate-pop" role="status">
            <x-icon name="circle-check" class="size-6 text-success" />
            <p class="font-semibold">{{ $admitted }} admitted. Ready for the next visitor.</p>
        </div>
    @endif

    @if ($result)
        <div @class(['card overflow-hidden animate-pop', 'ring-2 ring-success/40' => $valid, 'ring-2 ring-danger/30' => ! $valid]) role="alert">
            <div @class(['flex items-center gap-3 px-5 py-4 sm:px-6', 'bg-success text-white' => $valid, 'bg-danger text-white' => ! $valid])>
                <x-icon :name="$valid ? 'shield-check' : 'circle-x'" class="size-7" />
                <div>
                    <p class="text-lg font-extrabold">{{ $valid ? 'Valid pass' : 'Do not admit' }}</p>
                    <p class="text-sm text-white/90">{{ $message }}</p>
                </div>
            </div>

            @if ($pass)
                <div class="p-5 sm:p-6">
                    <div class="flex items-center gap-4">
                        <x-ui.avatar :name="$pass->visitor_name" size="lg" />
                        <div class="min-w-0">
                            <p class="truncate text-xl font-extrabold">{{ $pass->visitor_name }}</p>
                            <p class="text-sm text-muted">{{ humanize($pass->category) }} · {{ $pass->pass_type->label() }}</p>
                        </div>
                    </div>
                    <x-ui.dl class="mt-5" :items="[
                        'Going to' => $pass->property->identifier,
                        'Host' => $pass->host->name,
                        'Valid' => local_time($pass->valid_from, 'D j M, g:ia').' – '.local_time($pass->valid_until, 'D j M, g:ia'),
                        'Entries' => $pass->entries_count.($pass->max_entries ? ' of '.$pass->max_entries : ' (unlimited)'),
                        'Note' => $pass->notes,
                    ]" />

                    @if ($valid)
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label for="plate" class="text-[0.8125rem] font-semibold text-ink-soft">Vehicle plate</label>
                                <input id="plate" wire:model="vehiclePlate" class="control uppercase" placeholder="Optional">
                                @error('vehiclePlate')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="group" class="text-[0.8125rem] font-semibold text-ink-soft">People</label>
                                <input id="group" type="number" min="1" max="50" wire:model="groupSize" class="control">
                            </div>
                        </div>
                        <div class="mt-5 grid grid-cols-3 gap-2">
                            <x-ui.button type="button" variant="secondary" wire:click="clear">Cancel</x-ui.button>
                            <x-ui.button type="button" size="lg" class="col-span-2" icon="check" wire:click="admit" wire:loading.attr="disabled" wire:target="admit">Admit visitor</x-ui.button>
                        </div>
                    @endif
                </div>
            @endif

            @if (! $valid)
                <form wire:submit="deny" class="border-t border-line p-5 sm:p-6">
                    <label for="deny" class="text-[0.8125rem] font-semibold text-ink-soft">Record denied entry</label>
                    <div class="mt-1.5 flex gap-2">
                        <input id="deny" wire:model="denyReason" class="control flex-1" placeholder="Reason, e.g. expired pass">
                        <x-ui.button variant="danger-soft">Record</x-ui.button>
                    </div>
                    @error('denyReason')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    <button type="button" wire:click="clear" class="mt-3 text-sm font-semibold text-muted hover:text-ink">Clear and scan again</button>
                </form>
            @endif
        </div>
    @endif
</div>
