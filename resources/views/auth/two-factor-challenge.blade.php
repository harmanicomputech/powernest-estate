<x-layouts.guest title="Two-factor authentication" heading="Two-factor verification" subheading="Enter the 6-digit code from your authenticator app.">
    <div x-data="{ recovery: false }">
        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5">
            @csrf
            <div x-show="!recovery">
                <x-ui.input name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="text-center text-xl tracking-[0.4em] tnum" x-bind:disabled="recovery" />
            </div>
            <div x-show="recovery" x-cloak>
                <x-ui.input name="recovery_code" label="Recovery code" autocomplete="one-time-code" x-bind:disabled="!recovery" />
            </div>
            <x-ui.button class="w-full" size="lg">Verify</x-ui.button>
        </form>
        <button type="button" class="mt-6 w-full text-center text-sm font-semibold text-brand-700" x-on:click="recovery = !recovery" x-text="recovery ? 'Use an authentication code' : 'Use a recovery code'"></button>
    </div>
</x-layouts.guest>
