<?php

namespace App\Providers;

use App\Services\Ai\ClaudeVisionAnalyzer;
use App\Services\Ai\GeminiVisionAnalyzer;
use App\Services\Ai\StubVisionAnalyzer;
use App\Services\Ai\VisionAnalyzer;
use App\Services\Capture\CaptureDriver;
use App\Services\Capture\PlaywrightCaptureDriver;
use App\Services\Capture\StubCaptureDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One interface, several drivers. Swapping the model is one line in .env
        // and nothing else in the pipeline knows which one answered.
        $this->app->bind(VisionAnalyzer::class, fn () => match (config('ai.driver')) {
            'gemini' => new GeminiVisionAnalyzer,
            'claude' => new ClaudeVisionAnalyzer,
            default  => new StubVisionAnalyzer,
        });

        $this->app->bind(CaptureDriver::class, fn () => match (config('ai.capture_driver')) {
            'playwright' => new PlaywrightCaptureDriver,
            default      => new StubCaptureDriver,
        });
    }

    public function boot(): void
    {
        //
    }
}
