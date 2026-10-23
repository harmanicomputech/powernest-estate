<x-layouts.app :title="'Invoice '.$invoice->number">
    <x-ui.page-header :title="$invoice->number" :eyebrow="$invoice->property->identifier" :back="auth()->user()->can('billing.manage') ? route('manage.billing.invoices') : route('bills.index')">
        @if ($invoice->isOverdue())<x-ui.badge tone="danger" dot>Overdue</x-ui.badge>@else<x-ui.status :value="$invoice->status" />@endif
        <x-ui.button :href="route('pdf.invoice', $invoice)" variant="secondary" size="sm" icon="download">PDF</x-ui.button>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            <x-ui.card :padded="false">
                <div class="flex flex-wrap items-start justify-between gap-4 p-5 sm:p-6">
                    <div>
                        <p class="text-lg font-bold">{{ $invoice->description }}</p>
                        <p class="mt-1 text-sm text-muted">Issued {{ $invoice->issue_date->format('j M Y') }} · Due {{ $invoice->due_date->format('j M Y') }}</p>
                        @if ($invoice->billedUser)<p class="text-sm text-muted">Billed to {{ $invoice->billedUser->name }}</p>@endif
                    </div>
                    <div class="text-right">
                        <p class="eyebrow">Balance</p>
                        <p class="text-2xl font-extrabold tnum">{{ money($invoice->balance()) }}</p>
                    </div>
                </div>
                <table class="w-full text-sm">
                    <thead class="border-y border-line bg-canvas text-left text-xs font-semibold uppercase tracking-wide text-muted">
                        <tr><th class="px-5 py-2.5 sm:px-6">Item</th><th class="px-3 py-2.5 text-right">Qty</th><th class="px-5 py-2.5 text-right sm:px-6">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($invoice->items as $item)
                            <tr><td class="px-5 py-3 sm:px-6">{{ $item->description }}</td><td class="px-3 py-3 text-right tnum">{{ $item->quantity }}</td><td class="px-5 py-3 text-right font-semibold tnum sm:px-6">{{ money($item->amount) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-line text-sm">
                        @if ($invoice->adjustment_total)
                            <tr><td colspan="2" class="px-5 py-2 text-right text-muted sm:px-6">Discounts / waivers</td><td class="px-5 py-2 text-right tnum sm:px-6">−{{ money($invoice->adjustment_total) }}</td></tr>
                        @endif
                        <tr><td colspan="2" class="px-5 py-2 text-right text-muted sm:px-6">Total</td><td class="px-5 py-2 text-right font-bold tnum sm:px-6">{{ money($invoice->total) }}</td></tr>
                        <tr><td colspan="2" class="px-5 py-2 text-right text-muted sm:px-6">Paid</td><td class="px-5 py-2 text-right tnum sm:px-6">{{ money($invoice->amount_paid) }}</td></tr>
                    </tfoot>
                </table>
            </x-ui.card>

            @if ($invoice->allocations->isNotEmpty())
                <x-ui.card title="Payments" :padded="false">
                    @foreach ($invoice->allocations as $allocation)
                        <a href="{{ route('bills.receipt', $allocation->payment) }}" class="flex items-center justify-between border-t border-line px-5 py-3 text-sm hover:bg-canvas sm:px-6">
                            <span><span class="font-semibold">{{ humanize($allocation->payment->method) }}</span> <span class="text-muted">· {{ local_time($allocation->payment->paid_at, 'j M Y') }} · {{ $allocation->payment->reference }}</span></span>
                            <span class="flex items-center gap-2">@if ($allocation->payment->status === \App\Enums\PaymentStatus::Reversed)<x-ui.badge tone="danger">Reversed</x-ui.badge>@endif<span @class(['font-semibold tnum', 'line-through text-muted' => $allocation->payment->status === \App\Enums\PaymentStatus::Reversed])>{{ money($allocation->amount) }}</span></span>
                        </a>
                    @endforeach
                </x-ui.card>
            @endif

            @can('manage', $invoice)
                @include('manage.billing.partials.invoice-actions')
            @endcan
        </div>

        <div class="space-y-6 lg:col-span-2">
            @php
                $pendingSubmission = $invoice->submissions->first(fn ($s) => $s->status === \App\Enums\SubmissionStatus::Pending);
                $canPay = auth()->user()->can('pay', $invoice);
            @endphp
            @if ($invoice->isPayable() && $gateways && ! $pendingSubmission)
                <x-ui.card title="Pay online">
                    <form method="POST" action="{{ route('bills.pay', $invoice) }}" class="space-y-3" x-data="submitting" x-on:submit="submit">
                        @csrf
                        @foreach ($gateways as $key => $gateway)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line-strong p-3.5 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/60">
                                <input type="radio" name="gateway" value="{{ $key }}" class="accent-brand-700" @checked($loop->first)>
                                <span class="text-sm font-semibold">{{ $gateway->label() }}</span>
                            </label>
                        @endforeach
                        <x-ui.button class="w-full" size="lg" icon="lock" busy>Pay {{ money($invoice->balance()) }}</x-ui.button>
                    </form>
                </x-ui.card>
            @elseif ($invoice->status === \App\Enums\InvoiceStatus::Paid)
                <x-ui.alert tone="success" title="Paid in full">Thank you. Receipts are listed under Payments.</x-ui.alert>
            @elseif ($invoice->status === \App\Enums\InvoiceStatus::Void)
                <x-ui.alert tone="info" title="Voided">{{ $invoice->void_reason }}</x-ui.alert>
            @endif

            @if ($pendingSubmission)
                <x-ui.alert tone="info" title="Receipt under review" icon="file-check">
                    You uploaded a {{ money($pendingSubmission->amount) }} {{ strtolower(\App\Models\PaymentSubmission::METHODS[$pendingSubmission->method] ?? $pendingSubmission->method) }} receipt on {{ local_time($pendingSubmission->created_at, 'j M') }}. The estate office will verify it against their records and you'll be notified.
                </x-ui.alert>
            @elseif ($invoice->isPayable() && $canPay)
                <x-ui.card title="Paid offline?" description="Upload your bank transfer, POS or cash receipt. The estate office verifies it before the invoice is marked paid.">
                    <form method="POST" action="{{ route('bills.receipts.store', $invoice) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ file: null }" x-on:submit="$el.querySelector('[type=submit]').disabled = true">
                        @csrf
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.input name="amount" label="Amount paid" prefix="₦" inputmode="decimal" :value="\App\Support\Money::toDecimalString($invoice->balance())" required />
                            <x-ui.input name="paid_on" type="date" label="Date paid" :value="now()->toDateString()" :max="now()->toDateString()" required />
                        </div>
                        <x-ui.select name="method" label="How did you pay?" :options="\App\Models\PaymentSubmission::METHODS" value="bank_transfer" />
                        <x-ui.input name="payer_reference" label="Transaction / teller reference" optional placeholder="e.g. session ID or teller number" />
                        <div>
                            <p class="mb-1.5 text-[0.8125rem] font-semibold text-ink-soft">Receipt</p>
                            <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed border-line-strong px-4 py-5 text-center text-sm text-muted transition hover:border-brand-500">
                                <x-icon name="file-check" class="size-6" />
                                <span x-text="file ?? 'Tap to take a photo or choose a PDF'"></span>
                                <input type="file" name="receipt" accept="image/*,application/pdf" class="sr-only" required x-on:change="file = $event.target.files[0]?.name">
                            </label>
                            @error('receipt')<p class="mt-1.5 text-[0.8125rem] font-medium text-danger">{{ $message }}</p>@enderror
                        </div>
                        <x-ui.textarea name="note" label="Note" rows="2" optional />
                        <x-ui.button type="submit" variant="secondary" class="w-full" icon="file-check">Submit for verification</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            @php $pastSubmissions = $invoice->submissions->where('status', '!=', \App\Enums\SubmissionStatus::Pending); @endphp
            @if ($pastSubmissions->isNotEmpty())
                <x-ui.card title="Uploaded receipts" :padded="false">
                    @foreach ($pastSubmissions->sortByDesc('created_at') as $submission)
                        <div class="border-t border-line px-5 py-3 text-sm sm:px-6">
                            <div class="flex items-center justify-between gap-2"><span class="font-semibold tnum">{{ money($submission->amount) }} · {{ local_time($submission->created_at, 'j M Y') }}</span><x-ui.badge :tone="$submission->status->tone()" dot>{{ $submission->status->label() }}</x-ui.badge></div>
                            @if ($submission->review_note)<p class="mt-1 text-muted">{{ $submission->review_note }}</p>@endif
                        </div>
                    @endforeach
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.app>
