<x-layouts.guest title="Reset password" heading="Forgot your password?" subheading="Enter your email and we'll send a secure link to choose a new one.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" x-data="submitting" x-on:submit="submit">
        @csrf
        <x-ui.input name="email" type="email" label="Email address" autocomplete="email" required autofocus />
        <x-ui.button class="w-full" size="lg" busy>Email reset link</x-ui.button>
    </form>
    <p class="mt-8 text-center text-sm text-muted"><a href="{{ route('login') }}" class="link">Back to sign in</a></p>
</x-layouts.guest>
