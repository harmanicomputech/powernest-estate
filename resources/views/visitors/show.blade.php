@php $status = $pass->effectiveStatus(); $active = $status === \App\Enums\PassStatus::Active; @endphp
<x-layouts.app title="Pass for {{ $pass->visitor_name }}">
    <x-ui.page-header :title="$pass->visitor_name" :back="route('visitors.index')" :eyebrow="humanize($pass->category).' · '.$pass->pass_type->label()">
        <x-ui.status :value="$status" />
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <div class="card overflow-hidden">
                <div class="bg-brand-900 px-6 py-5 text-white">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-200/80">{{ $pass->estate->name }}</p>
                    <p class="mt-1 text-lg font-bold">{{ $pass->property->identifier }}</p>
                </div>
                <div class="relative p-6 text-center">
                    @if ($active)
                        <img src="{{ route('visitors.qr', $pass) }}" alt="QR code for {{ $pass->visitor_name }}" class="mx-auto aspect-square w-full max-w-[15rem] rounded-xl" width="240" height="240">
                        <p class="eyebrow mt-5">Access code</p>
                        <p class="mt-1 font-mono text-[2rem] font-extrabold tracking-[0.18em] tnum">{{ $pass->formattedCode() }}</p>
                    @else
                        <div class="grid aspect-square w-full max-w-[15rem] mx-auto place-items-center rounded-xl bg-sunken">
                            <div><x-icon name="circle-x" class="mx-auto size-10 text-faint" /><p class="mt-2 text-sm font-semibold text-muted">This pass is {{ strtolower($status->label()) }}</p></div>
                        </div>
                    @endif
                    <p class="mt-4 text-sm text-muted">{{ local_time($pass->valid_from, 'D j M, g:ia') }} → {{ local_time($pass->valid_until, 'D j M, g:ia') }}</p>
                </div>
                @if ($active)
                    <div class="grid grid-cols-2 gap-2 border-t border-line p-4">
                        <div x-data="share(@js(['title' => 'Visitor pass', 'text' => "Hi {$pass->visitor_name}, here's your pass for {$pass->estate->name} ({$pass->property->identifier}). Show the QR or code {$pass->formattedCode()} at the gate.", 'url' => $shareUrl]))">
                            <x-ui.button type="button" class="w-full" icon="share-2" x-on:click="share()"><span x-text="shared ? 'Copied' : 'Share'">Share</span></x-ui.button>
                        </div>
                        <div x-data="clipboard(@js($pass->code()))">
                            <x-ui.button type="button" variant="secondary" class="w-full" icon="copy" x-on:click="copy()"><span x-text="copied ? 'Copied' : 'Copy code'">Copy code</span></x-ui.button>
                        </div>
                        <a href="https://wa.me/?text={{ rawurlencode("Your visitor pass for {$pass->estate->name}: {$shareUrl}") }}" target="_blank" rel="noopener" class="col-span-2 inline-flex h-11 items-center justify-center gap-2 rounded-[var(--radius-control)] border border-line-strong text-sm font-semibold text-ink-soft hover:bg-sunken/60">
                            <x-icon name="message-circle" class="size-[1.125rem] text-[#25D366]" /> Send via WhatsApp
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6 lg:col-span-3">
            <x-ui.card title="Pass details">
                <x-ui.dl :items="[
                    'Visitor' => $pass->visitor_name,
                    'Phone' => $pass->visitor_phone,
                    'Destination' => $pass->property->identifier,
                    'Type' => $pass->pass_type->label(),
                    'People' => $pass->group_size,
                    'Vehicle' => $pass->vehicle_plate,
                    'Entries used' => $pass->entries_count.($pass->max_entries ? ' of '.$pass->max_entries : ''),
                    'Schedule' => $pass->recurrence ? collect($pass->recurrence['days'])->map(fn ($d) => ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$d])->join(', ').' · '.$pass->recurrence['start_time'].'–'.$pass->recurrence['end_time'] : null,
                    'Note' => $pass->notes,
                    'Created by' => $pass->host->name,
                ]" />
            </x-ui.card>

            <x-ui.card title="Gate activity" :padded="false">
                @forelse ($pass->visits as $visit)
                    <div class="flex items-center gap-3 border-t border-line px-5 py-3.5 text-sm sm:px-6">
                        <x-icon :name="$visit->status === \App\Enums\VisitStatus::Denied ? 'shield' : 'log-in'" class="size-4 text-muted" />
                        <span class="flex-1">{{ $visit->status === \App\Enums\VisitStatus::Denied ? 'Denied: '.$visit->denial_reason : 'Checked in'.($visit->gate ? ' at '.$visit->gate->name : '') }}</span>
                        <span class="text-muted">{{ local_time($visit->checked_in_at ?? $visit->created_at, 'D j M, g:ia') }}</span>
                    </div>
                    @if ($visit->checked_out_at)
                        <div class="flex items-center gap-3 border-t border-line px-5 py-3.5 text-sm sm:px-6">
                            <x-icon name="log-out" class="size-4 text-muted" /><span class="flex-1">Checked out</span><span class="text-muted">{{ local_time($visit->checked_out_at, 'D j M, g:ia') }}</span>
                        </div>
                    @endif
                @empty
                    <p class="border-t border-line px-5 py-5 text-sm text-muted sm:px-6">No gate activity yet. You'll be notified when your visitor arrives.</p>
                @endforelse
            </x-ui.card>

            @if ($active)
                <x-ui.confirm :action="route('visitors.revoke', $pass)" title="Revoke this pass?" message="The QR code and access code will stop working immediately. This cannot be undone." confirm="Revoke pass" variant="danger-soft" confirm-variant="danger" icon="circle-x">Revoke pass</x-ui.confirm>
            @endif
        </div>
    </div>
</x-layouts.app>
