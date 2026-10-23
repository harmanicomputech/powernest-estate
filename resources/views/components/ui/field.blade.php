@props(['label' => null, 'for' => null, 'error' => null, 'hint' => null, 'optional' => false])
@php $message = $error ? $errors->first($error) : null; @endphp
<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $for }}" class="flex items-baseline justify-between text-[0.8125rem] font-semibold text-ink-soft">
            <span>{{ $label }}</span>
            @if ($optional)<span class="text-xs font-medium text-faint">Optional</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($message)
        <p class="flex items-center gap-1.5 text-[0.8125rem] font-medium text-danger" id="{{ $for }}-error"><x-icon name="circle-alert" class="size-3.5 shrink-0" />{{ $message }}</p>
    @elseif ($hint)
        <p class="text-[0.8125rem] text-muted">{{ $hint }}</p>
    @endif
</div>
