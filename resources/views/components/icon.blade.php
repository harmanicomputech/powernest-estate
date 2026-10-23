@props(['name', 'stroke' => '1.75'])
{{ svg('lucide-'.$name, $attributes->get('class', 'size-5'), ['stroke-width' => $stroke, 'aria-hidden' => 'true', 'focusable' => 'false']) }}
