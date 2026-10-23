@props(['title', 'description' => null, 'back' => null, 'eyebrow' => null])
<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink"><x-icon name="arrow-left" class="size-4" />Back</a>
        @endif
        @if ($eyebrow)<p class="eyebrow mb-1.5">{{ $eyebrow }}</p>@endif
        <h1 class="text-[1.625rem] font-extrabold leading-tight tracking-tight sm:text-[1.875rem]">{{ $title }}</h1>
        @if ($description)<p class="mt-1.5 max-w-2xl text-[0.9375rem] text-muted">{{ $description }}</p>@endif
    </div>
    @if (! $slot->isEmpty())
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
