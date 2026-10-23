@props(['icon' => 'inbox', 'title', 'description' => null])
<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }}>
    <div class="relative mb-4">
        <div class="absolute inset-0 -m-3 rounded-full bg-brand-50/70"></div>
        <div class="relative grid size-12 place-items-center rounded-2xl border border-line bg-surface text-brand-700 shadow-[var(--shadow-card)]">
            <x-icon :name="$icon" class="size-5" />
        </div>
    </div>
    <h3 class="text-[0.9375rem] font-bold">{{ $title }}</h3>
    @if ($description)<p class="mt-1 max-w-sm text-sm text-muted">{{ $description }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-5">{{ $slot }}</div>@endif
</div>
