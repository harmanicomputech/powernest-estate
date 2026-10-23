@php
    $toasts = collect([
        session('success') ? ['tone' => 'success', 'icon' => 'circle-check', 'message' => session('success')] : null,
        session('status') ? ['tone' => 'success', 'icon' => 'circle-check', 'message' => session('status')] : null,
        session('error') ? ['tone' => 'danger', 'icon' => 'circle-x', 'message' => session('error')] : null,
        session('warning') ? ['tone' => 'warning', 'icon' => 'triangle-alert', 'message' => session('warning')] : null,
    ])->filter()->values();
    $statusText = ['verification-link-sent' => 'A new verification link has been sent to your email.', 'profile-information-updated' => 'Profile updated.', 'password-updated' => 'Password updated.', 'two-factor-authentication-enabled' => 'Scan the QR code to finish enabling two-factor authentication.', 'two-factor-authentication-confirmed' => 'Two-factor authentication is on.', 'two-factor-authentication-disabled' => 'Two-factor authentication is off.', 'recovery-codes-generated' => 'New recovery codes generated.'];
@endphp
<div class="pointer-events-none fixed inset-x-0 top-0 z-[100] flex flex-col items-center gap-2 px-4 pt-[calc(env(safe-area-inset-top)+0.75rem)] sm:items-end sm:pr-6"
     x-data="{ items: @js($toasts), push(item) { this.items.push(item); } }"
     x-on:toast.window="push($event.detail)" aria-live="polite">
    <template x-for="(toast, i) in items" :key="i">
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="opacity-0 -translate-y-4 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition duration-200" x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border border-line bg-surface px-4 py-3 shadow-[var(--shadow-raised)]">
            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full" :class="{ 'bg-success-soft text-success': toast.tone === 'success', 'bg-danger-soft text-danger': toast.tone === 'danger', 'bg-warning-soft text-warning': toast.tone === 'warning' }">
                <svg x-show="toast.tone === 'success'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                <svg x-show="toast.tone !== 'success'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 8v4m0 4h.01"/></svg>
            </span>
            <p class="flex-1 text-sm font-semibold text-ink" x-text="{{ Js::from($statusText) }}[toast.message] ?? toast.message"></p>
            <button type="button" x-on:click="show = false" class="-m-1 rounded p-1 text-faint hover:text-ink" aria-label="Dismiss"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
    </template>
</div>
