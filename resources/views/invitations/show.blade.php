<x-layouts.guest title="Invitation" :heading="'Join '.$invitation->estate->name" :subheading="'You have been invited as '.$invitation->role->label().($invitation->property ? ' for '.$invitation->property->identifier : '').'.'">
    @if ($problem)
        <x-ui.alert tone="warning">{{ $problem }}</x-ui.alert>
    @elseif (auth()->check())
        @if (strcasecmp(auth()->user()->email, $invitation->email) !== 0)
            <x-ui.alert tone="warning" title="Different account">This invitation was sent to {{ $invitation->email }}. You are signed in as {{ auth()->user()->email }}.</x-ui.alert>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<x-ui.button variant="secondary" class="w-full">Sign out and switch account</x-ui.button></form>
        @else
            @error('invitation')<x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>@enderror
            <form method="POST" action="{{ route('invitations.accept', $token) }}">@csrf
                <x-ui.button class="w-full" size="lg" icon="check">Accept invitation</x-ui.button>
            </form>
        @endif
    @else
        <div class="space-y-3">
            <x-ui.button :href="route('register')" class="w-full" size="lg">Create account</x-ui.button>
            <x-ui.button :href="route('login')" variant="secondary" class="w-full" size="lg">I already have an account</x-ui.button>
        </div>
        <p class="mt-4 text-center text-sm text-muted">Use <span class="font-semibold text-ink">{{ $invitation->email }}</span> so we can match the invitation.</p>
    @endif
</x-layouts.guest>
