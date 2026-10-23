{{--
    Inline gateway checkout. The browser only opens the provider's popup with public values.
    Whatever the popup reports, we ask our server to verify with the provider before showing success.
--}}
<div class="card overflow-hidden" x-data="{
        state: 'ready',
        error: null,
        open() {
            if (typeof window.PayazaCheckout === 'undefined') { this.error = 'The payment window could not load. Check your connection and try again.'; return; }
            this.error = null;
            this.state = 'paying';
            const checkout = window.PayazaCheckout.setup(@js($session->inline));
            checkout.setCallback((response) => { if (response && response.type === 'copy') return; this.confirm(); });
            checkout.setOnClose(() => this.confirm());
            checkout.showPopup();
        },
        async confirm() {
            if (this.state === 'confirming') return;
            this.state = 'confirming';
            try {
                const res = await fetch(@js($verifyUrl), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } });
                const data = await res.json();
                window.location.href = data.redirect ?? @js($fallbackUrl);
            } catch (e) {
                window.location.href = @js($fallbackUrl);
            }
        }
    }">
    <div class="bg-brand-900 px-6 py-6 text-white">
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand-200/80">Amount to pay</p>
        <p class="mt-1 text-[2.25rem] font-extrabold leading-none tracking-tight tnum">{{ money($amount) }}</p>
        <p class="mt-2 font-mono text-xs text-brand-200/80">{{ $reference }}</p>
    </div>
    <div class="p-6">
        <x-ui.dl :items="$lines" />
        <div class="mt-6" x-show="state !== 'confirming'">
            <x-ui.button type="button" size="lg" class="w-full" icon="lock" x-on:click="open()">Pay securely</x-ui.button>
            <p x-show="error" x-text="error" x-cloak class="mt-3 text-sm font-medium text-danger"></p>
            <p class="mt-4 flex items-center justify-center gap-1.5 text-xs text-muted"><x-icon name="shield-check" class="size-3.5" />Card or bank transfer via Payaza. Card details never touch PowerNest.</p>
        </div>
        <div x-show="state === 'confirming'" x-cloak class="mt-6 flex items-center justify-center gap-3 rounded-xl bg-canvas py-4 text-sm font-semibold">
            <x-icon name="loader" class="size-5 animate-spin text-brand-700" /> Confirming your payment with the provider…
        </div>
    </div>
</div>
<script src="{{ $session->script }}" defer></script>
