@props([
    'text' => 'NEW',
])

<span
    {{ $attributes->class(['label', 'label-info']) }}
    data-tooltip="true"
    title="Recently released feature"
>{{ $text }}</span>
