<x-layouts.app title="Account & security">
    <x-ui.page-header title="Account & security" />
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Profile">
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="space-y-4">@csrf @method('PUT')
                <x-ui.input name="name" label="Full name" :value="$user->name" required />
                <x-ui.input name="email" type="email" label="Email" :value="$user->email" required hint="Changing your email requires verifying the new address." />
                <x-ui.button>Save profile</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Password">
            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-4">@csrf @method('PUT')
                <x-ui.input name="current_password" type="password" label="Current password" autocomplete="current-password" required />
                <x-ui.input name="password" type="password" label="New password" autocomplete="new-password" required />
                <x-ui.input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />
                @if ($errors->updatePassword->any())<x-ui.alert tone="danger">{{ $errors->updatePassword->first() }}</x-ui.alert>@endif
                <x-ui.button>Update password</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Two-factor authentication" description="Strongly recommended for estate staff and administrators.">
            @if ($user->hasTwoFactorEnabled())
                <x-ui.alert tone="success" title="Enabled">You'll be asked for a code from your authenticator app when signing in.</x-ui.alert>
                @if (session('status') === 'recovery-codes-generated' || session('status') === 'two-factor-authentication-confirmed')
                    <div class="mt-4 rounded-xl bg-canvas p-4"><p class="text-sm font-semibold">Recovery codes — store them safely</p><div class="mt-2 grid grid-cols-2 gap-1 font-mono text-sm">@foreach ($user->recoveryCodes() as $code)<span>{{ $code }}</span>@endforeach</div></div>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('two-factor.recovery-codes') }}">@csrf<x-ui.button variant="secondary" size="sm">New recovery codes</x-ui.button></form>
                    <form method="POST" action="{{ route('two-factor.disable') }}">@csrf @method('DELETE')<x-ui.button variant="danger-soft" size="sm">Disable</x-ui.button></form>
                </div>
            @elseif ($user->two_factor_secret)
                <p class="text-sm text-muted">Scan this QR code with Google Authenticator, Authy or 1Password, then enter the 6-digit code.</p>
                <div class="mt-4 inline-block rounded-xl border border-line bg-white p-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex gap-2">@csrf
                    <input name="code" inputmode="numeric" maxlength="6" class="control w-40 text-center font-mono tracking-[0.3em]" placeholder="000000" required>
                    <x-ui.button>Confirm</x-ui.button>
                </form>
                @error('code', 'confirmTwoFactorAuthentication')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            @else
                <form method="POST" action="{{ route('two-factor.enable') }}">@csrf<x-ui.button icon="shield-check">Enable two-factor</x-ui.button></form>
            @endif
        </x-ui.card>

        <x-ui.card title="Email notifications" description="In-app notifications are always on. Payment, token and security emails are always sent.">
            <form method="POST" action="{{ route('account.notifications') }}" class="space-y-3">@csrf @method('PUT')
                @foreach ($categories as $key => $label)
                    <x-ui.checkbox name="mail[]" :value="$key" :label="$label" :checked="$user->wantsNotification($key, 'mail')" />
                @endforeach
                <x-ui.button variant="secondary">Save preferences</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
