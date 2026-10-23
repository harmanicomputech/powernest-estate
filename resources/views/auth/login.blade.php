<x-layouts.guest title="Sign in" heading="Welcome back" subheading="Sign in to manage visitors, power and payments for your home.">
    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="submitting" x-on:submit="submit">
        @csrf
        <x-ui.input name="email" type="email" label="Email address" autocomplete="username" required autofocus inputmode="email" />
        <div class="space-y-1.5" x-data="{ show: false }">
            <div class="flex items-baseline justify-between">
                <label for="password" class="text-[0.8125rem] font-semibold text-ink-soft">Password</label>
                <a href="{{ route('password.request') }}" class="text-[0.8125rem] font-semibold text-brand-700 hover:underline">Forgot password?</a>
            </div>
            <div class="relative">
                <input id="password" name="password" x-bind:type="show ? 'text' : 'password'" type="password" required autocomplete="current-password" class="control pr-12" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 grid w-11 place-items-center text-muted hover:text-ink" x-bind:aria-label="show ? 'Hide password' : 'Show password'"><x-icon name="eye" class="size-[1.125rem]" /></button>
            </div>
            @error('password')<p class="text-[0.8125rem] font-medium text-danger">{{ $message }}</p>@enderror
        </div>
        <x-ui.checkbox name="remember" label="Keep me signed in on this device" />
        <x-ui.button class="w-full" size="lg" busy>Sign in</x-ui.button>
    </form>
    <p class="mt-8 text-center text-sm text-muted">New to PowerNest Estate? <a href="{{ route('register') }}" class="link">Create an account</a></p>
</x-layouts.guest>
