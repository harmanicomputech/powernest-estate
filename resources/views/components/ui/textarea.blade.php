@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'optional' => false, 'rows' => 4])
@php $id = $attributes->get('id', $name); @endphp
<x-ui.field :label="$label" :for="$id" :error="$name" :hint="$hint" :optional="$optional">
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" {{ $attributes->except('id')->class('control resize-y leading-relaxed') }}>{{ old($name, $value) }}</textarea>
</x-ui.field>
