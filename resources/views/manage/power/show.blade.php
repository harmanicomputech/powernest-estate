@php use App\Enums\PowerPurchaseStatus as S; $s = $purchase->status; @endphp
<x-layouts.app :title="$purchase->reference">
    <x-ui.page-header :title="$purchase->reference" :eyebrow="'Electricity purchase · '.$purchase->user->name" :back="route('manage.power.index')"><x-ui.status :value="$s" /></x-ui.page-header>

    @if ($s->needsAttention())
        <x-ui.alert tone="warning" class="mb-6" :title="$s->label()">{{ $purchase->failure_reason }} @if($purchase->failure_code)<span class="font-mono text-xs">({{ $purchase->failure_code }})</span>@endif</x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Purchase">
                <div class="grid gap-x-8 sm:grid-cols-2">
                    <x-ui.dl :items="['Customer' => $purchase->user->name.' ('.$purchase->user->email.')', 'Property' => $purchase->property?->identifier ?? '—', 'Meter' => $purchase->meter_number, 'Vend amount' => money($purchase->vend_amount), 'Service charge' => money($purchase->service_charge), 'Expected total' => money($purchase->total_amount)]" />
                    <x-ui.dl :items="['Gateway' => ucfirst($purchase->gateway), 'Amount received' => $purchase->amount_paid !== null ? money($purchase->amount_paid) : 'Not confirmed', 'Gateway reference' => $purchase->gateway_reference, 'Channel' => humanize($purchase->payment_channel), 'Confirmed via' => humanize($purchase->payment_confirmed_via), 'Last verified' => $purchase->last_verified_at ? local_time($purchase->last_verified_at) : 'Never']" class="mt-3 sm:mt-0" />
                </div>
            </x-ui.card>

            @if ($purchase->token)
                <x-ui.card title="Token">
                    <p class="font-mono text-xl font-bold tracking-[0.08em]">{{ trim(chunk_split($purchase->token->token, 4, ' ')) }}</p>
                    <p class="mt-1 text-sm text-muted">{{ $purchase->token->units ? $purchase->token->units.' '.$purchase->token->unit_label.' · ' : '' }}Source: {{ humanize($purchase->token->source) }}{{ $purchase->token->recorder ? ' by '.$purchase->token->recorder->name : '' }} · {{ local_time($purchase->token->issued_at) }}</p>
                </x-ui.card>
            @endif

            <x-ui.card title="Vend attempts" :padded="false">
                @forelse ($purchase->vendAttempts as $attempt)
                    <div class="border-t border-line px-5 py-3 text-sm sm:px-6">
                        <div class="flex items-center justify-between"><span class="font-semibold">Attempt {{ $attempt->attempt }} · {{ humanize($attempt->outcome ?? 'running') }}</span><span class="text-muted">{{ local_time($attempt->started_at, 'j M, g:i:sa') }}{{ $attempt->initiator ? ' · '.$attempt->initiator->name : ' · automatic' }}</span></div>
                        @if ($attempt->error)<p class="mt-1 text-muted">{{ $attempt->error }}{{ $attempt->http_status ? ' (HTTP '.$attempt->http_status.')' : '' }}</p>@endif
                    </div>
                @empty
                    <p class="border-t border-line px-5 py-4 text-sm text-muted sm:px-6">No vend attempts.</p>
                @endforelse
            </x-ui.card>

            <x-ui.card title="State history" :padded="false">
                @foreach ($purchase->events as $event)
                    <div class="flex items-center gap-3 border-t border-line px-5 py-3 text-sm sm:px-6">
                        <span class="text-muted tnum">{{ local_time($event->created_at, 'j M, g:i:sa') }}</span>
                        <span class="flex-1">{{ $event->from_status?->label() ?? 'Created' }} → <span class="font-semibold">{{ $event->to_status->label() }}</span><span class="text-muted"> · {{ $event->note }}</span></span>
                        <span class="text-xs text-muted">{{ $event->actor?->name ?? 'System' }}</span>
                    </div>
                @endforeach
            </x-ui.card>

            @if ($webhooks->isNotEmpty())
                <x-ui.card title="Gateway webhooks received" :padded="false">
                    @foreach ($webhooks as $hook)
                        <details class="border-t border-line px-5 py-3 text-sm sm:px-6">
                            <summary class="cursor-pointer"><span class="font-semibold">{{ ucfirst($hook->gateway) }}</span> · {{ $hook->event_type }} · {{ local_time($hook->created_at, 'j M, g:i:sa') }} · {{ $hook->signature_valid ? 'authenticated' : 'unsigned (hint only)' }}</summary>
                            <pre class="mt-2 overflow-x-auto rounded-lg bg-canvas p-3 text-xs">{{ json_encode($hook->payload, JSON_PRETTY_PRINT) }}</pre>
                        </details>
                    @endforeach
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($canReconcile && ! $s->isFinal())
                <x-ui.card title="Reconcile" description="Every action is recorded in the audit log.">
                    <div class="space-y-3">
                        @if (! $s->isPaymentConfirmed() || $purchase->failure_code === 'amount_mismatch')
                            <form method="POST" action="{{ route('manage.power.reverify', $purchase) }}">@csrf<x-ui.button variant="secondary" class="w-full" icon="refresh-cw">Re-check with {{ ucfirst($purchase->gateway) }}</x-ui.button></form>
                        @endif

                        @if (in_array($s, [S::VendFailed, S::ReconciliationRequired], true) && $purchase->amount_paid >= $purchase->total_amount)
                            <x-ui.confirm :action="route('manage.power.retry', $purchase)" title="Retry vending?" message="Only retry after confirming in the Stron portal that NO token was issued for this meter and amount. Otherwise record the existing token instead." confirm="Retry vend" icon="zap" class="w-full">
                                Retry vend
                                <x-slot:fields>
                                    <x-ui.textarea name="note" rows="2" label="What did you check?" required />
                                    <x-ui.checkbox name="confirm_no_token" label="I confirmed no token was issued for this purchase" />
                                </x-slot:fields>
                            </x-ui.confirm>
                            <x-ui.confirm :action="route('manage.power.token', $purchase)" title="Record token from vendor portal" message="Use this when the vendor issued a token that did not reach PowerNest." confirm="Record token" icon="key-round" class="w-full">
                                Record existing token
                                <x-slot:fields>
                                    <x-ui.input name="token" label="Token (20 digits)" inputmode="numeric" required class="font-mono" />
                                    <x-ui.input name="units" label="Units (kWh)" inputmode="decimal" optional />
                                    <x-ui.textarea name="note" rows="2" label="Note" required />
                                </x-slot:fields>
                            </x-ui.confirm>
                        @endif

                        @if (in_array($s, [S::PendingPayment, S::Expired, S::PaymentFailed], true) || in_array($purchase->failure_code, ['amount_mismatch', 'currency_mismatch'], true))
                            @can('billing.approve')
                                <x-ui.confirm :action="route('manage.power.confirm-payment', $purchase)" title="Confirm payment manually" message="Only if the payment is visible in the gateway dashboard or bank statement. Requires finance approval rights." confirm="Confirm payment" icon="badge-check" class="w-full" variant="secondary">
                                    Confirm payment manually
                                    <x-slot:fields>
                                        <x-ui.input name="amount_received" label="Amount received (₦)" inputmode="decimal" required />
                                        <x-ui.input name="evidence" label="Evidence / gateway reference" required />
                                    </x-slot:fields>
                                </x-ui.confirm>
                            @endcan
                        @endif

                        @if ($s->canTransitionTo(S::Refunded))
                            <x-ui.confirm :action="route('manage.power.refund', $purchase)" title="Mark as refunded?" message="Record that the customer was refunded outside PowerNest. This closes the purchase." confirm="Mark refunded" confirm-variant="danger" variant="danger-soft" class="w-full">
                                Mark refunded
                                <x-slot:fields><x-ui.input name="note" label="Refund reference / note" required /></x-slot:fields>
                            </x-ui.confirm>
                        @endif
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
