{{-- A button that posts a form only after an explicit confirmation dialog. --}}
@props(['action', 'method' => 'POST', 'title' => 'Are you sure?', 'message' => null, 'confirm' => 'Confirm', 'variant' => 'secondary', 'confirmVariant' => 'primary', 'icon' => null, 'size' => 'md'])
<div x-data="{ open: false }" class="contents">
    <x-ui.button type="button" :variant="$variant" :icon="$icon" :size="$size" x-on:click="open = true" {{ $attributes }}>{{ $slot }}</x-ui.button>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-end justify-center sm:items-center sm:p-6" role="alertdialog" aria-modal="true" x-on:keydown.escape.window="open = false">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-brand-950/40" x-on:click="open = false"></div>
            <form method="POST" action="{{ $action }}" x-show="open" x-trap.noscroll="open" x-swipe-dismiss="open = false" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-data="submitting" x-on:submit="submit"
                  class="relative w-full max-w-md rounded-t-[1.5rem] bg-surface p-6 pb-safe shadow-[var(--shadow-raised)] sm:rounded-[1.25rem] sm:pb-6">
                @csrf
                @if (strtoupper($method) !== 'POST')
                    <input type="hidden" name="_method" value="{{ strtoupper($method) }}">
                @endif
                <h2 class="text-lg font-bold">{{ $title }}</h2>
                @if ($message)<p class="mt-2 text-sm text-muted">{{ $message }}</p>@endif
                @isset($fields)<div class="mt-4 space-y-4">{{ $fields }}</div>@endisset
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                    <x-ui.button :variant="$confirmVariant" busy>{{ $confirm }}</x-ui.button>
                </div>
            </form>
        </div>
    </template>
</div>
