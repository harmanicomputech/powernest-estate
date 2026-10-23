@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'optional' => false, 'prefix' => null])
@php $id = $attributes->get('id', $name); $invalid = $errors->has($name); @endphp
<x-ui.field :label="$label" :for="$id" :error="$name" :hint="$hint" :optional="$optional">
    @if ($prefix)
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-[0.9375rem] font-semibold text-muted">{{ $prefix }}</span>
            <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" aria-invalid="{{ $invalid ? 'true' : 'false' }}" @if($invalid) aria-describedby="{{ $id }}-error" @endif {{ $attributes->except('id')->class('control pl-10') }}>
        </div>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" aria-invalid="{{ $invalid ? 'true' : 'false' }}" @if($invalid) aria-describedby="{{ $id }}-error" @endif {{ $attributes->except('id')->class('control') }}>
    @endif
</x-ui.field>
