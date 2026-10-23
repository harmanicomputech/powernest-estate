@props(['value'])
<x-ui.badge :tone="$value->tone()" dot {{ $attributes }}>{{ $value->label() }}</x-ui.badge>
