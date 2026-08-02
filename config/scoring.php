<?php

/*
|--------------------------------------------------------------------------
| Health score weights
|--------------------------------------------------------------------------
| Six categories rolled into one number out of 100. These weights are shown
| in the app, because a number nobody can take apart is a number nobody
| should trust. The array must sum to 100 — a test enforces that.
*/

return [
    'weights' => App\Services\HealthScorer::WEIGHTS,

    /*
    | Rough build estimate per problem category, 1 (minutes) to 5 (days).
    | Used as the divisor in the priority formula. This is the weakest input
    | in the whole calculation — a wrong effort skews the ranking more than a
    | wrong severity does. See the note on card #99.
    */
    'effort' => [
        'cta'           => 1,
        'content'       => 2,
        'trust'         => 2,
        'ui'            => 3,
        'layout'        => 3,
        'mobile'        => 3,
        'accessibility' => 2,
        'performance'   => 4,
    ],
];
