<!DOCTYPE html>
<html lang="en">
<head><x-layouts.head title="Estate living, connected" /></head>
<body class="bg-canvas">
    <header class="mx-auto flex max-w-6xl items-center justify-between px-5 pt-safe sm:px-8">
        <div class="py-5"><x-brand.logo /></div>
        <div class="flex items-center gap-2">
            <x-ui.button :href="route('login')" variant="ghost">Sign in</x-ui.button>
            <x-ui.button :href="route('register')" class="hidden sm:inline-flex">Create account</x-ui.button>
        </div>
    </header>
    <main>
        <section class="mx-auto grid max-w-6xl items-center gap-12 px-5 pb-16 pt-10 sm:px-8 lg:grid-cols-2 lg:pt-20">
            <div class="animate-fade-up">
                <p class="eyebrow !text-gold-500">One estate · One platform</p>
                <h1 class="display mt-4 text-[2.75rem] leading-[1.02] text-ink sm:text-[3.75rem]">Everything your estate runs on, connected.</h1>
                <p class="mt-6 max-w-lg text-lg text-muted">Visitor passes, prepaid electricity, service charges, maintenance and security — for residents, managers and gate staff, in one secure app.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button :href="route('register')" size="lg" icon-right="arrow-right">Get started</x-ui.button>
                    <x-ui.button :href="route('login')" size="lg" variant="secondary">I have an account</x-ui.button>
                </div>
            </div>
            <div class="relative animate-fade-up [animation-delay:120ms]">
                <div class="absolute -inset-6 -z-10 rounded-[2.5rem] bg-gradient-to-br from-brand-100 via-canvas to-gold-100/60"></div>
                <div class="mx-auto max-w-sm space-y-3">
                    <div class="card p-5">
                        <div class="flex items-center justify-between"><span class="eyebrow">Visitor pass</span><x-ui.badge tone="success" dot>Active</x-ui.badge></div>
                        <div class="mt-4 flex items-center gap-4">
                            <div class="grid size-20 shrink-0 place-items-center rounded-xl bg-brand-900 text-gold-200"><x-icon name="qr-code" class="size-10" /></div>
                            <div><p class="font-bold">Kola Bankole</p><p class="text-sm text-muted">Guest · Today 4:00pm</p><p class="mt-1 font-mono text-lg font-extrabold tracking-[0.2em]">482 913</p></div>
                        </div>
                    </div>
                    <div class="rounded-[var(--radius-card)] bg-brand-900 p-5 text-white shadow-[var(--shadow-raised)]">
                        <div class="flex items-center gap-2 text-sm font-semibold text-gold-200"><x-icon name="zap" class="size-4" />Token ready</div>
                        <p class="mt-3 font-mono text-xl font-bold tracking-[0.08em]">5821 4407 1938 2265 0094</p>
                        <p class="mt-2 text-sm text-brand-100/80">₦10,000.00 · 44.4 kWh</p>
                    </div>
                    <p class="text-center text-xs text-faint">Illustration</p>
                </div>
            </div>
        </section>
        <section class="border-t border-line bg-surface">
            <div class="mx-auto grid max-w-6xl gap-8 px-5 py-14 sm:grid-cols-2 sm:px-8 lg:grid-cols-4">
                @foreach ([['qr-code', 'Digital access', 'QR and 6-digit passes verified by the server at every gate.'], ['zap', 'PowerNest electricity', 'Buy prepaid tokens with Payaza or Paystack, with automatic recovery.'], ['receipt', 'Estate billing', 'Service charges, receipts and a full payment history.'], ['wrench', 'Maintenance & security', 'Requests, work orders, incidents and announcements.']] as [$icon, $title, $body])
                    <div>
                        <span class="grid size-11 place-items-center rounded-2xl bg-brand-50 text-brand-700"><x-icon :name="$icon" class="size-5" /></span>
                        <h3 class="mt-4 font-bold">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </main>
    <footer class="mx-auto max-w-6xl px-5 py-8 text-sm text-faint sm:px-8">© {{ date('Y') }} PowerNest Estate</footer>
</body>
</html>
