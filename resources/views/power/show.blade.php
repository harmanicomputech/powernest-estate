<x-layouts.app title="Purchase {{ $purchase->reference }}">
    <x-ui.page-header :title="money($purchase->vend_amount)" :eyebrow="'Electricity · '.$purchase->reference" :back="route('power.index')" />

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <livewire:purchase-status :purchase="$purchase" />

            @if ($purchase->status === \App\Enums\PowerPurchaseStatus::PendingPayment && $purchase->user_id === auth()->id())
                <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                    <form method="POST" action="{{ route('power.purchases.verify', $purchase) }}" x-data="submitting" x-on:submit="submit">@csrf
                        <x-ui.button variant="secondary" icon="refresh-cw" busy class="w-full">I've paid — check status</x-ui.button>
                    </form>
                    <x-ui.button :href="route('power.purchases.pay', $purchase)" icon="credit-card">Continue payment</x-ui.button>
                </div>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Receipt">
                <x-ui.dl :items="[
                    'Reference' => $purchase->reference,
                    'Meter' => $purchase->meter_number,
                    'Property' => $purchase->meter->property->identifier,
                    'Electricity value' => money($purchase->vend_amount),
                    'Service charge' => money($purchase->service_charge),
                    'Total' => money($purchase->total_amount),
                    'Amount received' => $purchase->amount_paid ? money($purchase->amount_paid) : null,
                    'Payment' => ucfirst($purchase->gateway).($purchase->payment_channel ? ' · '.humanize($purchase->payment_channel) : ''),
                    'Paid at' => $purchase->paid_at ? local_time($purchase->paid_at) : null,
                    'Created' => local_time($purchase->created_at),
                ]" />
                @if ($purchase->status->isPaymentConfirmed())
                    <x-ui.button :href="route('pdf.purchase', $purchase)" variant="secondary" class="mt-5 w-full" icon="download">Download receipt (PDF)</x-ui.button>
                @endif
                @if ($purchase->token && $purchase->user_id === auth()->id())
                    <form method="POST" action="{{ route('power.purchases.resend', $purchase) }}" class="mt-2">@csrf
                        <x-ui.button variant="secondary" class="w-full" icon="mail">Email me this token</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            @if ($purchase->user_id === auth()->id() && ! in_array($purchase->status, [\App\Enums\PowerPurchaseStatus::TokenIssued, \App\Enums\PowerPurchaseStatus::PendingPayment], true))
                <x-ui.card title="Need help?">
                    <form method="POST" action="{{ route('power.purchases.support', $purchase) }}" class="space-y-3">@csrf
                        <x-ui.textarea name="message" rows="3" placeholder="Tell us what happened, e.g. I was debited but no token" />
                        <x-ui.button variant="secondary" class="w-full" icon="life-buoy">Contact support</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
