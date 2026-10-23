<?php

/*
|--------------------------------------------------------------------------
| Company feature presets
|--------------------------------------------------------------------------
|
| Each company's deployment sets APP_FEATURE_PRESET. `php artisan app:init`
| stores the preset's values for every flag not chosen yet, and from then on the
| stored value wins: changing the preset later changes nothing that is already
| set. Until app:init runs, the preset's values apply directly.
|
| Each preset matches what that company's codebase did before flags existed, so
| moving a company onto this codebase changes nothing it sees.
|
| A new company with no preset chooses every flag on the Company Features page
| (super_admin) or interactively with `php artisan app:init`.
|
*/

use App\Features\Feature;

return [

    'preset' => env('APP_FEATURE_PRESET'),

    'presets' => [

        'homefinder' => [
            Feature::Recruitment->value => true,
            Feature::GoogleLogin->value => true,
            Feature::OvertimeStatutoryCaps->value => true,
        ],

        'partnershub' => [
            Feature::Recruitment->value => true,
            Feature::GoogleLogin->value => true,
            Feature::OvertimeStatutoryCaps->value => true,
        ],

        // kgtb-pm removed recruitment and Google sign-in at import, and never got
        // the overtime caps. Confirm the caps with kgtb before it moves here.
        'kgtb' => [
            Feature::Recruitment->value => false,
            Feature::GoogleLogin->value => false,
            Feature::OvertimeStatutoryCaps->value => false,
        ],

    ],

];
