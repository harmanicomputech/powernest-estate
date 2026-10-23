@props(['name', 'title' => null, 'maxWidth' => 'max-w-lg'])
<div x-data="{ open: false }" x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true" x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false" x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
            <div x-show="open" x-transition.opacity.duration.200ms class="absolute inset-0 bg-brand-950/40 backdrop-blur-[2px]" x-on:click="open = false"></div>
            <div x-show="open" x-trap.noscroll.inert="open" x-swipe-dismiss="open = false"
                 x-transition:enter="transition ease-[var(--ease-out-soft)] duration-300" x-transition:enter-start="translate-y-8 opacity-0 sm:translate-y-0 sm:scale-95" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative w-full {{ $maxWidth }} rounded-t-[1.5rem] bg-surface p-6 pb-safe shadow-[var(--shadow-raised)] sm:rounded-[1.25rem] sm:pb-6">
                <div class="mx-auto -mt-2 mb-4 h-1 w-10 rounded-full bg-line-strong sm:hidden"></div>
                @if ($title)
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <h2 class="text-lg font-bold">{{ $title }}</h2>
                        <button type="button" x-on:click="open = false" class="-m-2 rounded-lg p-2 text-muted hover:bg-sunken" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                    </div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </template>
</div>
