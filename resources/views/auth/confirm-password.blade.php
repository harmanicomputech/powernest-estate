<x-layouts.guest title="Confirm password" heading="Confirm it's you" subheading="This is a protected area. Please re-enter your password to continue.">
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5" x-data="submitting" x-on:submit="submit">
        @csrf
        <x-ui.input name="password" type="password" label="Password" autocomplete="current-password" required autofocus />
        <x-ui.button class="w-full" size="lg" busy>Confirm</x-ui.button>
    </form>
</x-layouts.guest>
