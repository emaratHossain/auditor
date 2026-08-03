<?php

namespace App\Providers;

use App\Services\Ai\ClaudeVisionAnalyzer;
use App\Services\Ai\GeminiVisionAnalyzer;
use App\Services\Ai\StubVisionAnalyzer;
use App\Services\Ai\VisionAnalyzer;
use App\Services\Capture\CaptureDriver;
use App\Services\Capture\PlaywrightCaptureDriver;
use App\Services\Capture\StubCaptureDriver;
use App\Services\Rewrite\ClaudeCopyRewriter;
use App\Services\Rewrite\CopyRewriter;
use App\Services\Rewrite\StubCopyRewriter;
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

        // The rewrite runs on click rather than in the pipeline, so it gets its
        // own switch — the vision pass can stay on stub while this one is real.
        $this->app->bind(CopyRewriter::class, fn () => match (config('ai.rewrite_driver')) {
            'claude' => new ClaudeCopyRewriter,
            default  => new StubCopyRewriter,
        });
    }

    public function boot(): void
    {
        //
    }
}
