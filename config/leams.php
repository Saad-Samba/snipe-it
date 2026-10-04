<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Model fieldset overrides
    |--------------------------------------------------------------------------
    |
    | LEAMS initially governs custom-field schemas at the Category / Asset
    | Family level. Keep the underlying Snipe-IT model override capability
    | available for a later rollout without exposing it during phase one.
    |
    */
    'model_fieldset_overrides' => env('LEAMS_MODEL_FIELDSET_OVERRIDES', false),

    /*
    |--------------------------------------------------------------------------
    | Platform dongle asset models
    |--------------------------------------------------------------------------
    |
    | These physical Asset Models identify the Platform dongle subset. Keep
    | this deployment configuration separate from the catalog itself so the
    | catalog owners retain normal control of model records and names.
    |
    */
    'platform_dongle_model_names' => array_filter(array_map(
        'trim',
        explode(',', env('LEAMS_PLATFORM_DONGLE_MODEL_NAMES', 'Vector KEYMAN,SIEMENS DONGLE')),
    )),
];
