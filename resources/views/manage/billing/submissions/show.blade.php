@php $pending = $submission->status === \App\Enums\SubmissionStatus::Pending; $invoice = $submission->invoice; @endphp
<x-layouts.app :title="'Receipt '.$submission->reference">
    <x-ui.page-header :title="money($submission->amount).' · '.$submission->submitter->name" :eyebrow="'Receipt '.$submission->reference" :back="route('manage.billing.submissions.index')">
        <x-ui.badge :tone="$submission->status->tone()" dot>{{ $submission->status->label() }}</x-ui.badge>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <x-ui.card title="Uploaded receipt" :padded="false" class="overflow-hidden">
                @if ($doc = $submission->receipt)
                    <x-slot:action><x-ui.button :href="route('documents.show', $doc)" target="_blank" size="sm" variant="secondary" icon="external-link">Open</x-ui.button></x-slot:action>
                    <div class="border-t border-line bg-sunken">
                        @if ($doc->isImage())
                            <img src="{{ route('documents.show', $doc) }}" alt="Receipt uploaded by {{ $submission->submitter->name }}" class="mx-auto max-h-[70dvh] w-auto object-contain">
                        @else
                            <iframe src="{{ route('documents.show', $doc) }}" title="Receipt PDF" class="h-[70dvh] w-full"></iframe>
                        @endif
                    </div>
                @else
                    <p class="border-t border-line px-6 py-6 text-sm text-muted">No file attached.</p>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Resident's details">
                <x-ui.dl :items="[
                    'Submitted by' => $submission->submitter->name.' · '.$submission->submitter->email,
                    'Property' => $submission->property->identifier,
                    'Method' => \App\Models\PaymentSubmission::METHODS[$submission->method] ?? $submission->method,
                    'Date paid' => $submission->paid_on->format('j M Y'),
                    'Their reference' => $submission->payer_reference,
                    'Amount claimed' => money($submission->amount),
                    'Note' => $submission->note,
                    'Uploaded' => local_time($submission->created_at),
                ]" />
            </x-ui.card>

            <x-ui.card title="Invoice {{ $invoice->number }}">
                <x-ui.dl :items="['For' => $invoice->description, 'Total' => money($invoice->total), 'Already paid' => money($invoice->amount_paid), 'Balance' => money($invoice->balance()), 'Status' => $invoice->status->label()]" />
                <a href="{{ route('bills.show', $invoice) }}" class="link mt-3 inline-block text-sm">Open invoice</a>
            </x-ui.card>

            @if ($pending)
                @if ($submission->submitted_by === auth()->id())
                    <x-ui.alert tone="warning">You submitted this receipt, so another staff member must review it.</x-ui.alert>
                @else
                    <x-ui.card title="Decision">
                        <form method="POST" action="{{ route('manage.billing.submissions.approve', $submission) }}" class="space-y-3" x-data="submitting" x-on:submit="submit">@csrf
                            <x-ui.input name="amount" label="Amount received" prefix="₦" inputmode="decimal" :value="\App\Support\Money::toDecimalString(min($submission->amount, $invoice->balance()))" required hint="Change this if the bank shows a different amount." />
                            <x-ui.input name="note" label="Verification note" optional placeholder="e.g. Matched GTB statement 14 Oct" />
                            <x-ui.button class="w-full" icon="check" busy>Approve and apply payment</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('manage.billing.submissions.reject', $submission) }}" class="mt-5 space-y-3 border-t border-line pt-5">@csrf
                            <x-ui.input name="reason" label="Reason for rejecting" required placeholder="e.g. Not found on statement / unreadable" />
                            <x-ui.button variant="danger-soft" class="w-full" icon="x">Reject</x-ui.button>
                        </form>
                    </x-ui.card>
                @endif
            @else
                <x-ui.alert :tone="$submission->status->tone()" :title="$submission->status->label().' by '.$submission->reviewer?->name">
                    {{ local_time($submission->reviewed_at) }}{{ $submission->review_note ? ' — '.$submission->review_note : '' }}
                    @if ($submission->payment) <a class="link" href="{{ route('bills.receipt', $submission->payment) }}">View receipt {{ $submission->payment->reference }}</a>@endif
                </x-ui.alert>
            @endif

            @if ($history->isNotEmpty())
                <x-ui.card title="Other receipts for this invoice" :padded="false">
                    @foreach ($history as $other)
                        <a href="{{ route('manage.billing.submissions.show', $other) }}" class="flex items-center justify-between border-t border-line px-5 py-3 text-sm hover:bg-canvas sm:px-6"><span>{{ money($other->amount) }} · {{ local_time($other->created_at, 'j M') }}</span><x-ui.badge :tone="$other->status->tone()">{{ $other->status->label() }}</x-ui.badge></a>
                    @endforeach
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
