@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'optional' => false])
@php $id = $attributes->get('id', $name); $selected = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value); @endphp
<x-ui.field :label="$label" :for="$id" :error="$name" :hint="$hint" :optional="$optional">
    <div class="relative">
        <select name="{{ $name }}" id="{{ $id }}" aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" {{ $attributes->except('id')->class('control appearance-none pr-10') }}>
            @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        <x-icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
    </div>
</x-ui.field>
