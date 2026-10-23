@props(['title' => null, 'heading' => null, 'subheading' => null])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="min-h-full bg-surface">
<div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
    <aside class="relative hidden overflow-hidden bg-brand-900 lg:flex lg:flex-col lg:justify-between lg:p-12">
        <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.07]" aria-hidden="true">
            <defs><pattern id="grid" width="48" height="48" patternUnits="userSpaceOnUse"><path d="M48 0H0v48" fill="none" stroke="#fff" stroke-width="1"/></pattern></defs>
            <rect width="100%" height="100%" fill="url(#grid)"/>
        </svg>
        <div class="pointer-events-none absolute -bottom-40 -right-24 size-[34rem] rounded-full bg-brand-600/40 blur-3xl"></div>
        <x-brand.logo inverse class="relative" />
        <div class="relative max-w-md">
            <p class="eyebrow !text-gold-300">One estate. One platform.</p>
            <h2 class="display mt-4 text-[2.75rem] leading-[1.05] text-white">Everything your estate runs on, connected.</h2>
            <ul class="mt-10 space-y-4 text-[0.9375rem] text-brand-100/85">
                <li class="flex items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-white/10"><x-icon name="qr-code" class="size-4 text-gold-300" /></span>Digital visitor passes verified at the gate</li>
                <li class="flex items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-white/10"><x-icon name="zap" class="size-4 text-gold-300" /></span>Prepaid electricity tokens in seconds</li>
                <li class="flex items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-white/10"><x-icon name="receipt" class="size-4 text-gold-300" /></span>Service charges, receipts and requests in one place</li>
            </ul>
        </div>
        <p class="relative text-xs text-brand-200/60">© {{ date('Y') }} PowerNest Estate</p>
    </aside>
    <main class="flex flex-col px-5 pb-10 pt-safe sm:px-10">
        <div class="pt-6 lg:hidden"><x-brand.logo /></div>
        <div class="mx-auto flex w-full max-w-[25rem] flex-1 flex-col justify-center py-10 animate-fade-up">
            @if ($heading)
                <h1 class="text-[1.75rem] font-extrabold tracking-tight">{{ $heading }}</h1>
                @if ($subheading)<p class="mt-2 text-[0.9375rem] text-muted">{{ $subheading }}</p>@endif
            @endif
            @if (session('status'))
                <x-ui.alert tone="success" class="mt-6">{{ session('status') }}</x-ui.alert>
            @endif
            <div class="mt-8">{{ $slot }}</div>
        </div>
    </main>
</div>
</body>
</html>
