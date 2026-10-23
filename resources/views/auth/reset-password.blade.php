<x-layouts.guest title="Choose a new password" heading="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5" x-data="submitting" x-on:submit="submit">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input name="email" type="email" label="Email address" autocomplete="email" required :value="$request->email" />
        <x-ui.input name="password" type="password" label="New password" autocomplete="new-password" required />
        <x-ui.input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />
        <x-ui.button class="w-full" size="lg" busy>Reset password</x-ui.button>
    </form>
</x-layouts.guest>
