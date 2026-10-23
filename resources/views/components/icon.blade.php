@props(['name', 'stroke' => '1.75'])
{{ svg('lucide-'.$name, $attributes->get('class', 'size-5'), array_merge($attributes->except('class')->getAttributes(), ['stroke-width' => $stroke, 'aria-hidden' => 'true', 'focusable' => 'false'])) }}
