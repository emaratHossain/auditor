<?php

/*
|--------------------------------------------------------------------------
| Stall detection
|--------------------------------------------------------------------------
| How long an audit may sit before the app calls it stuck and says so. These
| are deployment facts, not product facts: the right number depends entirely
| on how the queue worker is started on that machine.
|
| A local `php artisan queue:work` picks a job up in under a second, so 45s
| means "nothing is listening" with near-certainty. A worker started from a
| cron cannot answer that fast — there is a gap between one worker exiting
| and the next starting, and anything shorter than that gap reports a healthy
| deployment as broken. See docs/deployment-cpanel.md, step 7.
|
| PENDING must exceed the worst-case gap. RUNNING is a different failure — a
| stage started and went quiet — and deserves far more rope, because a real
| capture with Lighthouse measured 72-121 seconds on live pages.
*/

return [
    'stall' => [
        'pending_seconds' => (int) env('AUDIT_PENDING_TIMEOUT', 45),

        'running_seconds' => (int) env('AUDIT_RUNNING_TIMEOUT', 600),
    ],
];
