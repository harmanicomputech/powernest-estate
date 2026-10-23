@props(['title' => null, 'wide' => false])
@php
    $user = auth()->user();
    $navigation = app(\App\Support\Navigation::class);
    $sections = $navigation->sections($user);
    $bottom = $navigation->bottomBar($user);
    $estate = $currentEstate ?? null;
    $estates = $user?->estates()->orderBy('estates.name')->get() ?? collect();
    $context = app(\App\Support\EstateContext::class);
    $roleLabel = $context->roles()->map->label()->join(' · ');
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="min-h-full" x-data="{ nav: false }" x-on:keydown.escape.window="nav = false">

{{-- Connection state --}}
<div x-data x-show="!$store.network.online" x-cloak x-transition class="fixed inset-x-0 top-0 z-[90] flex items-center justify-center gap-2 bg-ink px-4 py-2 pt-safe text-sm font-semibold text-white">
    <x-icon name="cloud-off" class="size-4" /> You're offline. Actions will not be sent until you reconnect.
</div>

{{-- Desktop sidebar --}}
<aside class="fixed inset-y-0 left-0 z-40 hidden w-[17rem] flex-col border-r border-line bg-surface lg:flex">
    <div class="flex h-16 items-center px-6"><a href="{{ route('dashboard') }}"><x-brand.logo /></a></div>
    @if ($estate)
        <div class="px-4 pb-2">
            <x-estate-switcher :estate="$estate" :estates="$estates" :role-label="$roleLabel" />
        </div>
    @endif
    <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-4" aria-label="Main">
        @foreach ($sections as $section)
            <div>
                <p class="eyebrow mb-2 px-3">{{ $section['label'] }}</p>
                <ul class="space-y-0.5">
                    @foreach ($section['items'] as $item)
                        @php $active = request()->routeIs($item['active']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" @class([
                                'group flex items-center gap-3 rounded-[0.7rem] px-3 py-2 text-[0.875rem] font-semibold transition',
                                'bg-brand-50 text-brand-800' => $active,
                                'text-ink-soft hover:bg-sunken hover:text-ink' => ! $active,
                            ]) @if($active) aria-current="page" @endif>
                                <x-icon :name="$item['icon']" @class(['size-[1.125rem]', 'text-brand-700' => $active, 'text-muted group-hover:text-ink-soft' => ! $active]) />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
    <div class="border-t border-line p-4">
        <x-user-menu :user="$user" placement="up" />
    </div>
</aside>

<div class="lg:pl-[17rem]">
    {{-- Top bar --}}
    <header class="sticky top-0 z-30 border-b border-line/70 bg-canvas/85 pt-safe backdrop-blur-md supports-[backdrop-filter]:bg-canvas/70">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="lg:hidden"><x-brand.mark class="size-9" /></a>
            <div class="min-w-0 flex-1 lg:hidden">
                @if ($estate)
                    <p class="truncate text-[0.9375rem] font-bold leading-tight">{{ $estate->name }}</p>
                    <p class="truncate text-xs text-muted">{{ $roleLabel }}</p>
                @endif
            </div>
            <div class="hidden flex-1 lg:block">
                @isset($toolbar){{ $toolbar }}@endisset
            </div>
            @if ($estate)
                <livewire:notification-bell />
            @endif
            <div class="hidden lg:block"><x-user-menu :user="$user" compact /></div>
            <button type="button" class="grid size-10 place-items-center rounded-xl text-ink-soft hover:bg-sunken lg:hidden" x-on:click="nav = true" aria-label="Open menu">
                <x-icon name="menu" class="size-5" />
            </button>
        </div>
    </header>

    <main @class(['mx-auto px-4 pb-28 pt-6 sm:px-6 sm:pt-8 lg:px-8 lg:pb-16', 'max-w-6xl' => ! $wide, 'max-w-7xl' => $wide])>
        <div class="animate-fade-up">{{ $slot }}</div>
    </main>
</div>

{{-- Mobile bottom bar --}}
@if ($bottom)
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 pb-safe backdrop-blur-md lg:hidden" aria-label="Quick">
        <ul class="mx-auto grid max-w-md" style="grid-template-columns: repeat({{ count($bottom) + 1 }}, minmax(0, 1fr))">
            @foreach ($bottom as $item)
                @php $active = request()->routeIs($item['active']); @endphp
                <li>
                    <a href="{{ route($item['route']) }}" class="flex flex-col items-center gap-1 pb-2 pt-2.5 text-[0.6875rem] font-semibold {{ $active ? 'text-brand-700' : 'text-muted' }}" @if($active) aria-current="page" @endif>
                        @if (! empty($item['primary']))
                            <span class="-mt-6 grid size-12 place-items-center rounded-2xl bg-brand-700 text-gold-200 shadow-[0_8px_20px_-6px_rgb(16_56_42/0.6)] ring-4 ring-surface"><x-icon :name="$item['icon']" class="size-[1.375rem]" /></span>
                        @else
                            <span @class(['grid h-7 w-12 place-items-center rounded-full transition', 'bg-brand-50' => $active])><x-icon :name="$item['icon']" class="size-5" /></span>
                        @endif
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
            <li>
                <button type="button" x-on:click="nav = true" class="flex w-full flex-col items-center gap-1 pb-2 pt-2.5 text-[0.6875rem] font-semibold text-muted">
                    <span class="grid h-7 w-12 place-items-center"><x-icon name="ellipsis" class="size-5" /></span>
                    More
                </button>
            </li>
        </ul>
    </nav>
@endif

{{-- Mobile navigation sheet --}}
<div x-show="nav" x-cloak class="fixed inset-0 z-[60] lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
    <div x-show="nav" x-transition.opacity class="absolute inset-0 bg-brand-950/40" x-on:click="nav = false"></div>
    <div x-show="nav" x-trap.noscroll="nav"
         x-transition:enter="transition duration-300 ease-[var(--ease-out-soft)]" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="absolute inset-x-0 bottom-0 max-h-[88dvh] overflow-y-auto rounded-t-[1.5rem] bg-surface pb-safe">
        <div class="sticky top-0 z-10 flex items-center justify-between bg-surface px-5 pb-3 pt-4">
            <x-user-menu :user="$user" compact inline />
            <button type="button" x-on:click="nav = false" class="grid size-10 place-items-center rounded-xl hover:bg-sunken" aria-label="Close menu"><x-icon name="x" class="size-5" /></button>
        </div>
        @if ($estate && $estates->count() > 1)
            <div class="px-5 pb-2"><x-estate-switcher :estate="$estate" :estates="$estates" :role-label="$roleLabel" /></div>
        @endif
        <div class="space-y-5 px-5 pb-6 pt-2">
            @foreach ($sections as $section)
                <div>
                    <p class="eyebrow mb-2">{{ $section['label'] }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($section['items'] as $item)
                            @php $active = request()->routeIs($item['active']); @endphp
                            <a href="{{ route($item['route']) }}" @class(['flex items-center gap-2.5 rounded-xl border px-3 py-3 text-sm font-semibold', 'border-brand-200 bg-brand-50 text-brand-800' => $active, 'border-line text-ink-soft' => ! $active])>
                                <x-icon :name="$item['icon']" class="size-[1.125rem] shrink-0 {{ $active ? 'text-brand-700' : 'text-muted' }}" />
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <div class="grid grid-cols-2 gap-2 border-t border-line pt-5">
                <a href="{{ route('account.edit') }}" class="flex items-center gap-2.5 rounded-xl border border-line px-3 py-3 text-sm font-semibold text-ink-soft"><x-icon name="circle-user-round" class="size-[1.125rem] text-muted" />Account</a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="flex w-full items-center gap-2.5 rounded-xl border border-line px-3 py-3 text-sm font-semibold text-danger"><x-icon name="log-out" class="size-[1.125rem]" />Sign out</button>
                </form>
            </div>
        </div>
    </div>
</div>

<x-toasts />
</body>
</html>
