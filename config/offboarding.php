<?php

return [
    'fallback_recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OFFBOARDING_FALLBACK_RECIPIENTS', ''))
    ))),
];
