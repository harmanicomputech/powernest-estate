@php
    $hour = (int) now($estate->timezone())->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<x-layouts.app title="Dashboard">
    <div class="mb-6 sm:mb-8">
        <p class="eyebrow">{{ now($estate->timezone())->format('l, j F') }}</p>
        <h1 class="display mt-1.5 text-[2rem] leading-tight text-ink sm:text-[2.5rem]">{{ $greeting }}, {{ $user->firstName() }}</h1>
    </div>

    @if (empty($panels))
        <x-ui.card>
            <x-ui.empty icon="layout-dashboard" title="Nothing to show yet" description="Your role in {{ $estate->name }} doesn't have a dashboard. Use the menu to get to your tools." />
        </x-ui.card>
    @endif

    <div class="space-y-10">
        @isset($panels['resident'])
            @include('dashboard.partials.resident', ['data' => $panels['resident']])
        @endisset
        @isset($panels['security'])
            @include('dashboard.partials.security', ['data' => $panels['security']])
        @endisset
        @isset($panels['management'])
            @include('dashboard.partials.management', ['data' => $panels['management']])
        @endisset
        @isset($panels['facility'])
            @include('dashboard.partials.facility', ['data' => $panels['facility']])
        @endisset
    </div>
</x-layouts.app>
