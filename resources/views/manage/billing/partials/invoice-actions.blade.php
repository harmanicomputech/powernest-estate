@if ($invoice->isPayable())
    <x-ui.card title="Finance actions">
        <div class="flex flex-wrap gap-2">
            <x-ui.confirm :action="route('manage.billing.invoices.payment', $invoice)" title="Record offline payment" message="For bank transfers, cash or POS. A receipt is sent to the resident." confirm="Record payment" icon="banknote" size="sm">
                Record payment
                <x-slot:fields>
                    <x-ui.input name="amount" label="Amount" prefix="₦" :value="\App\Support\Money::toDecimalString($invoice->balance())" required />
                    <x-ui.select name="method" label="Method" :options="['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'pos' => 'POS', 'cheque' => 'Cheque']" />
                    <x-ui.input name="external_reference" label="Bank / receipt reference" required />
                    <x-ui.input name="paid_at" type="datetime-local" label="Paid at" :value="now()->format('Y-m-d\TH:i')" required />
                </x-slot:fields>
            </x-ui.confirm>
            <x-ui.confirm :action="route('manage.billing.invoices.adjustment', $invoice)" title="Request discount or waiver" message="Requires approval by a different finance approver." confirm="Submit for approval" size="sm" variant="secondary">
                Discount / waiver
                <x-slot:fields>
                    <x-ui.select name="type" label="Type" :options="['discount' => 'Discount', 'waiver' => 'Waiver']" />
                    <x-ui.input name="amount" label="Amount" prefix="₦" required />
                    <x-ui.input name="reason" label="Reason" required />
                </x-slot:fields>
            </x-ui.confirm>
            @if ($invoice->billedUser)
                <form method="POST" action="{{ route('manage.billing.invoices.remind', $invoice) }}">@csrf<x-ui.button size="sm" variant="secondary" icon="bell">Send reminder</x-ui.button></form>
            @endif
            @if ($invoice->amount_paid === 0)
                <x-ui.confirm :action="route('manage.billing.invoices.void', $invoice)" title="Void this invoice?" message="Voiding is recorded in the ledger and cannot be undone." confirm="Void invoice" confirm-variant="danger" size="sm" variant="danger-soft">
                    Void
                    <x-slot:fields><x-ui.input name="reason" label="Reason" required /></x-slot:fields>
                </x-ui.confirm>
            @endif
        </div>
    </x-ui.card>
@endif
