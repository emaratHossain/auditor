<?php

namespace App\Http\Controllers;

/**
 * The numbers that pre-fill the metrics form.
 *
 * Reads the same config file the demo seeder reads, so what is shown on stage
 * and what ships in the seed cannot drift apart.
 */
class DemoMetricsController extends Controller
{
    public function __invoke()
    {
        // Not a model, so there is nothing to leak — but the shape still matches
        // every other endpoint the React app talks to.
        return response()->json(['data' => config('demo-analytics')]);
    }
}
