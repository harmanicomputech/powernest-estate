<?php

return [
    'sets' => [],
    'class' => '',
    'attributes' => [],
    'fallback' => '',
    // The app renders icons through its own <x-icon> component (resources/views/components/icon.blade.php)
    // using the svg() helper, so the package's Blade components are not registered.
    'components' => [
        'disabled' => true,
        'default' => null,
    ],
];
