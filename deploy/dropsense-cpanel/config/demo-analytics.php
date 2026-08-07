<?php

/*
|--------------------------------------------------------------------------
| The demo dataset
|--------------------------------------------------------------------------
| One realistic set of numbers, shaped like a GA4 export with the two things
| GA4 cannot give you without custom events — per-section scroll depth and a
| real button click rate — plus the two Clarity signals.
|
| ONE file, read by BOTH the endpoint that pre-fills the form and the demo
| seeder. That is deliberate: if they read different sources, what you show on
| stage and what ships in the seed drift apart, and you find out in front of
| the judges.
|
| These are demo numbers and the report says so. The honesty comes from the
| label, not from hiding them — and the form stays editable, so a real number
| can be typed in live if someone asks.
*/

return [
    'label'  => 'Demo analytics — a sample GA4 + Clarity dataset, not your own numbers',
    'source' => 'demo',

    'visitors'        => 18_450,
    'bounce_rate'     => 64.2,
    'conversion_rate' => 1.8,

    // 2.1% against 96% reach is the whole story: they find the button and ignore it.
    'cta_click_rate' => 2.1,

    'mobile_share'       => 68.0,
    'mobile_bounce_rate' => 79.4,

    // How far down visitors actually get. Pricing at 21% is buried.
    'section_reach' => [
        'Hero'         => 96.0,
        'Features'     => 71.0,
        'Testimonials' => 44.0,
        'Pricing'      => 21.0,
        'FAQ'          => 12.0,
    ],

    // Clarity's two signals. Features collects 340 rage clicks because the
    // feature cards have a hover effect and no link behind them.
    'rage_clicks' => [
        'Hero'         => 12,
        'Features'     => 340,
        'Testimonials' => 8,
        'Pricing'      => 41,
        'FAQ'          => 3,
    ],

    'dead_clicks' => [
        'Hero'         => 30,
        'Features'     => 512,
        'Testimonials' => 14,
        'Pricing'      => 60,
        'FAQ'          => 5,
    ],
];
