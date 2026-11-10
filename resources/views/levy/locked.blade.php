<x-layouts.guest title="Levy payment required" heading="Your access is on hold" :subheading="'Your yearly estate levy for '.$estate->name.' is overdue. Pay by bank transfer and tap “I have paid” to unlock the app once the estate office confirms it.'">
    <div class="space-y-3">
        @foreach ($invoices as $invoice)
            @php $reviewing = $underReview->contains($invoice); @endphp
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-bold">{{ $invoice->chargeType->name }}</p>
                        <p class="text-sm text-muted">{{ $invoice->property->identifier }} · due {{ $invoice->due_date->format('j M Y') }}</p>
                    </div>
                    <x-ui.badge :tone="$reviewing ? 'info' : 'danger'">{{ $reviewing ? 'Receipt under review' : 'Overdue' }}</x-ui.badge>
                </div>
                <p class="mt-4 text-[1.75rem] font-extrabold tracking-tight tnum">{{ money($invoice->balance()) }}</p>
                @if ($reviewing)
                    <p class="mt-2 text-sm text-muted">The estate office is checking the receipt you uploaded. You'll get full access as soon as it is approved.</p>
                @endif
                <div class="mt-5 grid gap-2">
                    <x-ui.button :href="route('bills.show', $invoice)" icon="landmark" size="lg">Pay by bank transfer</x-ui.button>
                    <x-ui.button :href="route('pdf.invoice', $invoice)" variant="secondary" icon="download">Download invoice</x-ui.button>
                </div>
            </div>
        @endforeach
        <p class="pt-2 text-center text-sm text-muted">
            Already paid? Open the bill and tap “I have paid” — the estate office will confirm it.
            @if ($estate->contact_phone) Questions: <a class="link" href="tel:{{ $estate->contact_phone }}">{{ $estate->contact_phone }}</a>@endif
        </p>
        <form method="POST" action="{{ route('logout') }}" class="pt-2 text-center">@csrf
            <button class="text-sm font-semibold text-muted hover:text-ink">Sign out</button>
        </form>
    </div>
</x-layouts.guest>
