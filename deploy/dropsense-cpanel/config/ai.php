<?php

return [
    // stub | gemini | claude  — stub costs nothing and needs no network, which is
    // both the build-week default and the safety net for demo day.
    'driver' => env('AI_DRIVER', 'stub'),

    // stub | claude — a separate switch from the vision call, so you can run the
    // expensive vision pass on stub while testing real rewrites, and flip the
    // rewrite back to stub on demo day if the venue network is bad.
    'rewrite_driver' => env('AI_REWRITE_DRIVER', env('AI_DRIVER', 'stub')),

    // A hard ceiling. If an audit would cost more than this, nothing is saved.
    'max_cost_per_audit' => (float) env('AI_MAX_COST_PER_AUDIT', 0.25),

    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),

        // Paid rates per million tokens. These defaults are the published
        // gemini-2.5-flash rates; every model prices differently, so if you
        // change GEMINI_MODEL, check the current rate card and set these too.
        // Getting them wrong only misreports the cost — but the ceiling above is
        // enforced against that same figure, so a wrong rate is a wrong guard.
        'input_per_million'  => (float) env('GEMINI_INPUT_PER_MILLION', 0.30),
        'output_per_million' => (float) env('GEMINI_OUTPUT_PER_MILLION', 2.50),
    ],

    'claude' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
    ],

    // stub | playwright
    'capture_driver' => env('CAPTURE_DRIVER', 'stub'),

    // Full path to node. Leave blank to auto-discover. A queue worker or
    // php-fpm usually has a narrower PATH than your shell.
    'node_binary' => env('NODE_BINARY'),
];
