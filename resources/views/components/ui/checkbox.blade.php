@props(['name', 'label', 'checked' => false, 'description' => null, 'value' => '1'])
<label class="flex cursor-pointer items-start gap-3">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked)) {{ $attributes->class('mt-0.5 size-[1.125rem] shrink-0 rounded-[0.35rem] border-line-strong text-brand-700 accent-brand-700 focus:ring-brand-500') }}>
    <span class="text-sm">
        <span class="font-semibold text-ink">{{ $label }}</span>
        @if ($description)<span class="block text-muted">{{ $description }}</span>@endif
    </span>
</label>
@error($name)<p class="mt-1 text-[0.8125rem] font-medium text-danger">{{ $message }}</p>@enderror
