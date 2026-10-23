<x-layouts.guest title="Create account" heading="Create your account" subheading="Use the email address your estate invited, or request access after signing up.">
    <form method="POST" action="{{ route('register') }}" class="space-y-5" x-data="submitting" x-on:submit="submit">
        @csrf
        <x-ui.input name="name" label="Full name" autocomplete="name" required autofocus />
        <x-ui.input name="email" type="email" label="Email address" autocomplete="email" required :value="session('invitation_email')" />
        <x-ui.input name="phone" type="tel" label="Phone number" autocomplete="tel" inputmode="tel" placeholder="0803 000 0000" optional />
        <x-ui.input name="password" type="password" label="Password" autocomplete="new-password" required hint="At least 8 characters with letters and numbers." />
        <x-ui.input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" required />
        <x-ui.checkbox name="terms" label="I agree to the terms and privacy notice" description="Your data is used to operate estate services and is never sold." />
        <x-ui.button class="w-full" size="lg" busy>Create account</x-ui.button>
    </form>
    <p class="mt-8 text-center text-sm text-muted">Already have an account? <a href="{{ route('login') }}" class="link">Sign in</a></p>
</x-layouts.guest>
