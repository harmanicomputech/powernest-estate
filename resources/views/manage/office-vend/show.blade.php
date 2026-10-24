<x-layouts.app :title="'Token '.$purchase->reference">
    <x-ui.page-header :title="$purchase->token ? 'Token issued' : $purchase->status->label()" :eyebrow="$purchase->reference" :back="route('manage.office-vend.create')" data-print-hide>
        @if ($purchase->token)
            <x-ui.button type="button" variant="secondary" icon="printer" onclick="window.print()">Print</x-ui.button>
            <x-ui.button :href="route('pdf.purchase', $purchase)" variant="secondary" icon="download">PDF</x-ui.button>
        @endif
    </x-ui.page-header>

    <div class="mx-auto max-w-xl space-y-5 print:max-w-none">
        @if ($purchase->token)
            <div class="rounded-[var(--radius-card)] bg-brand-900 p-6 text-white print:border print:border-black print:bg-white print:text-black">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-200/80 print:text-black">Electricity token</p>
                <p class="mt-2 break-all font-mono text-[1.65rem] font-bold leading-snug tracking-[0.08em] tnum sm:text-[2rem]">{{ trim(chunk_split($purchase->token->token, 4, ' ')) }}</p>
                <p class="mt-3 text-sm text-brand-100/80 print:text-black">{{ $purchase->token->units ? $purchase->token->units.' '.$purchase->token->unit_label.' · ' : '' }}Meter {{ $purchase->meter_number }}</p>
            </div>
        @else
            <x-ui.alert tone="warning" :title="$purchase->status->label()">{{ $purchase->failure_reason ?? 'The vending service has not returned a token yet. Check Power operations shortly.' }}</x-ui.alert>
        @endif

        <x-ui.card>
            <x-ui.dl :items="[
                'Value' => money($purchase->vend_amount).' (no payment)',
                'Meter' => $purchase->meter_number,
                'Property' => $purchase->property?->identifier ?? '—',
                'Sent to' => $purchase->user->name.' · '.$purchase->user->email,
                'Issued by' => $purchase->issuer?->name,
                'Reason' => $purchase->office_note,
                'Date' => local_time($purchase->token?->issued_at ?? $purchase->created_at),
            ]" />
        </x-ui.card>

        @if ($purchase->token)
            <form method="POST" action="{{ route('manage.office-vend.resend', $purchase) }}" data-print-hide>@csrf
                <x-ui.button variant="secondary" icon="mail" class="w-full">Email the token again</x-ui.button>
            </form>
        @endif
        <x-ui.button :href="route('manage.office-vend.create')" variant="ghost" icon="plus" class="w-full" data-print-hide>Issue another token</x-ui.button>
    </div>
</x-layouts.app>
