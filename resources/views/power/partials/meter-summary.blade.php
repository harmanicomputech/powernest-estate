{{-- Verified meter details as returned by the vending service. --}}
<div class="flex items-start gap-3.5 rounded-xl border border-brand-200 bg-brand-50/60 p-4">
    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-brand-700"><x-icon name="badge-check" class="size-5" /></span>
    <div class="min-w-0 flex-1 text-sm">
        <p class="font-mono text-base font-bold tracking-wide tnum">{{ $meter->meter_number }}</p>
        <p class="mt-0.5 font-semibold text-ink">{{ $meter->vendor_customer_name ?? 'Name not provided by the meter service' }}</p>
        @if ($meter->vendor_address || $meter->property)
            <p class="text-muted">{{ $meter->vendor_address ?? $meter->property?->identifier }}</p>
        @endif
        <p class="mt-1 text-xs font-semibold text-brand-700">Verified{{ $meter->meter_type ? ' · '.$meter->meter_type : '' }}</p>
    </div>
</div>
