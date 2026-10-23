<x-layouts.guest title="Verify email" heading="Check your inbox" subheading="We sent a verification link to {{ auth()->user()->email }}. Open it to activate your account.">
    @if (session('status') === 'verification-link-sent')
        <x-ui.alert tone="success" class="mb-5">A new verification link is on its way.</x-ui.alert>
    @endif
    <div class="space-y-3">
        <form method="POST" action="{{ route('verification.send') }}" x-data="submitting" x-on:submit="submit">@csrf
            <x-ui.button class="w-full" size="lg" busy icon="mail">Resend verification email</x-ui.button>
        </form>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <x-ui.button variant="ghost" class="w-full">Sign out</x-ui.button>
        </form>
    </div>
</x-layouts.guest>
