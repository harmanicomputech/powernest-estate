<x-layouts.guest title="Join your estate" heading="Join your estate" subheading="Your account isn't linked to an estate yet. If you received an invitation email, open the link in it. Otherwise, request access below.">
    @if (session('success'))<x-ui.alert tone="success" class="mb-5">{{ session('success') }}</x-ui.alert>@endif
    <form method="POST" action="{{ route('onboarding.request') }}" class="space-y-5" x-data="submitting" x-on:submit="submit">
        @csrf
        <x-ui.input name="estate_code" label="Estate code" required class="uppercase" hint="Ask your estate office for this code." />
        <x-ui.input name="property_identifier" label="House / unit number" required class="uppercase" placeholder="e.g. A-101" />
        <x-ui.select name="relationship" label="I am the" :options="\App\Enums\OccupancyRelationship::options()" value="tenant" />
        <x-ui.textarea name="message" label="Note to management" rows="2" optional />
        <x-ui.button class="w-full" size="lg" busy>Request access</x-ui.button>
    </form>
    @if ($requests->isNotEmpty())
        <div class="mt-8 space-y-2">
            <p class="eyebrow">Your requests</p>
            @foreach ($requests as $req)
                <div class="flex items-center justify-between rounded-xl border border-line px-4 py-3 text-sm"><span><span class="font-semibold">{{ $req->estate->name }}</span> · {{ $req->property?->identifier }}</span><x-ui.badge :tone="['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$req->status]">{{ ucfirst($req->status) }}</x-ui.badge></div>
            @endforeach
        </div>
    @endif
    @if (auth()->user()->isPlatformAdmin())
        <x-ui.button :href="route('platform.dashboard')" variant="secondary" class="mt-6 w-full" icon="layers">Go to platform console</x-ui.button>
    @endif
    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf<button class="text-sm font-semibold text-muted hover:text-ink">Sign out</button></form>
</x-layouts.guest>
