<section class="space-y-6" aria-label="My home">
    @foreach ($data['awaiting'] as $visit)
        <a href="{{ route('visitors.walk-ins.show', $visit) }}" class="flex items-center gap-4 rounded-[var(--radius-card)] border border-[#f3d9a8] bg-warning-soft p-4 animate-pop">
            <span class="relative grid size-11 shrink-0 place-items-center rounded-xl bg-white text-warning">
                <x-icon name="bell-ring" class="size-5" />
                <span class="absolute -right-0.5 -top-0.5 size-3 animate-ping rounded-full bg-warning/60"></span>
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-ink">{{ $visit->visitor_name }} is at the gate</p>
                <p class="text-sm text-ink-soft">Security is waiting for you to approve or decline entry.</p>
            </div>
            <x-icon name="chevron-right" class="size-5 text-warning" />
        </a>
    @endforeach

    @foreach ($data['annualDues'] as $due)
        @php $invoice = $due['invoice']; @endphp
        @if ($due['state'] === 'paid')
            <div class="flex items-center gap-4 rounded-[var(--radius-card)] border border-[#bfe5cf] bg-success-soft p-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-success"><x-icon name="badge-check" class="size-6" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-ink">{{ $due['name'] }} — paid for {{ $due['year'] }}</p>
                    <p class="text-sm text-ink-soft">{{ $due['property'] }} · thank you, you're covered for the year.</p>
                </div>
                <a href="{{ route('pdf.invoice', $invoice) }}" class="text-sm font-semibold text-success hover:underline">Invoice</a>
            </div>
        @elseif ($due['state'] === 'under_review')
            <a href="{{ route('bills.show', $invoice) }}" class="flex items-center gap-4 rounded-[var(--radius-card)] border border-[#c6d8f2] bg-info-soft p-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-info"><x-icon name="file-check" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-ink">{{ $due['name'] }} {{ $due['year'] }} — receipt under review</p>
                    <p class="text-sm text-ink-soft">The estate office is verifying your offline payment.</p>
                </div>
                <x-icon name="chevron-right" class="size-5 text-info" />
            </a>
        @else
            <a href="{{ route('bills.show', $invoice) }}" class="flex items-center gap-4 rounded-[var(--radius-card)] border border-[#f3d9a8] bg-warning-soft p-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-warning"><x-icon name="shield" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-ink">{{ $due['name'] }} {{ $due['year'] }} is due</p>
                    <p class="text-sm text-ink-soft">{{ money($invoice->balance()) }} outstanding · pay online or upload your bank receipt.</p>
                </div>
                <span class="text-sm font-bold text-warning">Pay</span>
            </a>
        @endif
    @endforeach

    {{-- Hero --}}
    <div class="relative overflow-hidden rounded-[1.5rem] bg-brand-900 p-5 text-white sm:p-7">
        <div class="pointer-events-none absolute -right-16 -top-24 size-72 rounded-full bg-brand-600/40 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 size-56 rounded-full bg-gold-400/15 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-200/80">{{ $data['properties']->pluck('identifier')->join(' · ') ?: 'Your home' }}</p>
                <p class="mt-3 text-sm text-brand-100/80">Outstanding estate charges</p>
                <p class="mt-1 text-[2.25rem] font-extrabold leading-none tracking-tight tnum">{{ money($data['outstanding']) }}</p>
                @if ($data['nextInvoice'])
                    <p class="mt-2 text-sm text-brand-100/80">Next due {{ $data['nextInvoice']->due_date->format('j M') }} · {{ $data['nextInvoice']->number }}</p>
                @else
                    <p class="mt-2 flex items-center gap-1.5 text-sm text-brand-100/80"><x-icon name="circle-check" class="size-4 text-gold-300" />You're all paid up</p>
                @endif
            </div>
            @if ($data['outstanding'] > 0)
                <x-ui.button :href="route('bills.index')" variant="gold" icon-right="arrow-right">Pay bills</x-ui.button>
            @endif
        </div>
        <div class="relative mt-6 grid grid-cols-4 gap-2 border-t border-white/10 pt-5">
            @can('visitors.invite')
                <a href="{{ route('visitors.create') }}" class="group flex flex-col items-center gap-2 rounded-xl p-2 text-center text-xs font-semibold text-brand-50 transition hover:bg-white/5"><span class="grid size-11 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/10 transition group-hover:bg-white/15"><x-icon name="user-plus" class="size-5 text-gold-200" /></span>Invite</a>
            @endcan
            @can('power.purchase')
                <a href="{{ route('power.buy') }}" class="group flex flex-col items-center gap-2 rounded-xl p-2 text-center text-xs font-semibold text-brand-50 transition hover:bg-white/5"><span class="grid size-11 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/10 transition group-hover:bg-white/15"><x-icon name="zap" class="size-5 text-gold-200" /></span>Buy power</a>
            @endcan
            @can('maintenance.request')
                <a href="{{ route('maintenance.create') }}" class="group flex flex-col items-center gap-2 rounded-xl p-2 text-center text-xs font-semibold text-brand-50 transition hover:bg-white/5"><span class="grid size-11 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/10 transition group-hover:bg-white/15"><x-icon name="wrench" class="size-5 text-gold-200" /></span>Repair</a>
            @endcan
            @can('facilities.book')
                <a href="{{ route('facilities.index') }}" class="group flex flex-col items-center gap-2 rounded-xl p-2 text-center text-xs font-semibold text-brand-50 transition hover:bg-white/5"><span class="grid size-11 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/10 transition group-hover:bg-white/15"><x-icon name="calendar" class="size-5 text-gold-200" /></span>Book</a>
            @endcan
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            @can('visitors.invite')
                <x-ui.card title="Visitor passes" description="Active and upcoming access" :padded="false">
                    <x-slot:action><x-ui.button :href="route('visitors.create')" size="sm" variant="secondary" icon="plus">New pass</x-ui.button></x-slot:action>
                    @forelse ($data['activePasses'] as $pass)
                        <x-ui.row-link :href="route('visitors.show', $pass)" icon="qr-code" class="border-t border-line">
                            <p class="truncate font-semibold">{{ $pass->visitor_name }}</p>
                            <p class="truncate text-sm text-muted">{{ humanize($pass->category) }} · {{ local_time($pass->valid_from, 'D j M, g:ia') }}</p>
                            <x-slot:aside><span class="font-mono text-sm font-bold tracking-wider text-ink-soft tnum">{{ $pass->formattedCode() }}</span></x-slot:aside>
                        </x-ui.row-link>
                    @empty
                        <x-ui.empty icon="user-plus" title="No upcoming visitors" description="Create a pass and share the QR code or 6-digit code with your guest." class="border-t border-line !py-8" />
                    @endforelse
                </x-ui.card>
            @endcan

            @if ($data['openRequests']->isNotEmpty())
                <x-ui.card title="Your maintenance requests" :padded="false">
                    <x-slot:action><a href="{{ route('maintenance.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">View all</a></x-slot:action>
                    @foreach ($data['openRequests'] as $request)
                        <x-ui.row-link :href="route('maintenance.show', $request)" icon="wrench" tone="info" class="border-t border-line">
                            <p class="truncate font-semibold">{{ $request->title }}</p>
                            <p class="text-sm text-muted">{{ $request->reference }} · {{ $request->created_at->diffForHumans() }}</p>
                            <x-slot:aside><x-ui.status :value="$request->status" /></x-slot:aside>
                        </x-ui.row-link>
                    @endforeach
                </x-ui.card>
            @endif

            @if ($data['recentVisits']->isNotEmpty())
                <x-ui.card title="Recent arrivals" :padded="false">
                    <ul class="divide-y divide-line border-t border-line">
                        @foreach ($data['recentVisits'] as $visit)
                            <li class="flex items-center gap-3.5 px-5 py-3 sm:px-6">
                                <x-ui.avatar :name="$visit->visitor_name" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ $visit->visitor_name }}</p>
                                    <p class="text-xs text-muted">{{ local_time($visit->checked_in_at, 'D j M, g:ia') }}</p>
                                </div>
                                <x-ui.status :value="$visit->status" />
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            @can('power.purchase')
                <x-ui.card :padded="false" class="overflow-hidden">
                    <div class="flex items-center justify-between px-5 pt-5 sm:px-6 sm:pt-6">
                        <div class="flex items-center gap-2.5">
                            <span class="grid size-9 place-items-center rounded-xl bg-gold-50 text-gold-600"><x-icon name="zap" class="size-[1.125rem]" /></span>
                            <h2 class="text-[0.9375rem] font-bold">Electricity</h2>
                        </div>
                        <a href="{{ route('power.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">Open</a>
                    </div>
                    <div class="px-5 pb-5 pt-4 sm:px-6 sm:pb-6">
                        @if ($purchase = $data['lastPurchase'])
                            <a href="{{ route('power.purchases.show', $purchase) }}" class="block rounded-xl border border-line bg-canvas p-4 transition hover:border-line-strong">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-muted">Last purchase · {{ $purchase->created_at->diffForHumans() }}</span>
                                    <x-ui.status :value="$purchase->status" />
                                </div>
                                <p class="mt-2 text-xl font-extrabold tnum">{{ money($purchase->vend_amount) }}</p>
                                @if ($purchase->token)
                                    <p class="mt-1 font-mono text-sm font-semibold tracking-wider text-ink-soft">{{ trim(chunk_split($purchase->token->token, 4, ' ')) }}</p>
                                @else
                                    <p class="mt-1 text-sm text-muted">Meter {{ $purchase->meter_number }}</p>
                                @endif
                            </a>
                        @else
                            <p class="text-sm text-muted">Buy prepaid tokens for your meter in a few taps — no separate app needed.</p>
                        @endif
                        <x-ui.button :href="route('power.buy')" class="mt-4 w-full" icon="zap">Buy electricity</x-ui.button>
                    </div>
                </x-ui.card>
            @endcan

            @if ($data['bookings']->isNotEmpty())
                <x-ui.card title="Upcoming bookings" :padded="false">
                    @foreach ($data['bookings'] as $booking)
                        <div class="flex items-center gap-3.5 border-t border-line px-5 py-3.5 sm:px-6">
                            <div class="grid w-11 shrink-0 place-items-center rounded-xl border border-line py-1 text-center">
                                <span class="text-[0.625rem] font-bold uppercase text-danger">{{ local_time($booking->starts_at, 'M') }}</span>
                                <span class="text-base font-extrabold leading-none tnum">{{ local_time($booking->starts_at, 'j') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ $booking->facility->name }}</p>
                                <p class="text-xs text-muted">{{ local_time($booking->starts_at, 'g:ia') }} – {{ local_time($booking->ends_at, 'g:ia') }}</p>
                            </div>
                            <x-ui.status :value="$booking->status" />
                        </div>
                    @endforeach
                </x-ui.card>
            @endif

            <x-ui.card title="From the estate" :padded="false">
                <x-slot:action><a href="{{ route('announcements.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">All</a></x-slot:action>
                @forelse ($data['announcements'] as $announcement)
                    <a href="{{ route('announcements.show', $announcement) }}" class="block border-t border-line px-5 py-3.5 transition hover:bg-canvas sm:px-6">
                        <div class="flex items-center gap-2">
                            @if ($announcement->is_pinned)<x-icon name="pin" class="size-3.5 text-gold-500" />@endif
                            <x-ui.badge :tone="$announcement->category->tone()">{{ $announcement->category->label() }}</x-ui.badge>
                            <span class="text-xs text-faint">{{ $announcement->published_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-1.5 line-clamp-1 text-sm font-semibold">{{ $announcement->title }}</p>
                    </a>
                @empty
                    <p class="border-t border-line px-5 py-6 text-sm text-muted sm:px-6">No announcements right now.</p>
                @endforelse
            </x-ui.card>
        </div>
    </div>
</section>
