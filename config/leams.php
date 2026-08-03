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
];
