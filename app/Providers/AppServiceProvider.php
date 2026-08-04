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
use App\Services\Rewrite\GeminiCopyRewriter;
use App\Services\Rewrite\StubCopyRewriter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One interface, several drivers. Swapping the model is one line in .env
        // and nothing else in the pipeline knows which one answered.
        $this->app->bind(VisionAnalyzer::class, fn () => match (config('ai.driver')) {
            'stub'   => new StubVisionAnalyzer,
            'gemini' => new GeminiVisionAnalyzer,
            'claude' => new ClaudeVisionAnalyzer,
            default  => $this->reject('AI_DRIVER', config('ai.driver'), ['stub', 'gemini', 'claude']),
        });

        $this->app->bind(CaptureDriver::class, fn () => match (config('ai.capture_driver')) {
            'stub'       => new StubCaptureDriver,
            'playwright' => new PlaywrightCaptureDriver,
            default      => $this->reject('CAPTURE_DRIVER', config('ai.capture_driver'), ['stub', 'playwright']),
        });

        // The rewrite runs on click rather than in the pipeline, so it gets its
        // own switch — the vision pass can stay on stub while this one is real.
        $this->app->bind(CopyRewriter::class, fn () => match (config('ai.rewrite_driver')) {
            'stub'   => new StubCopyRewriter,
            'gemini' => new GeminiCopyRewriter,
            'claude' => new ClaudeCopyRewriter,
            default  => $this->reject('AI_REWRITE_DRIVER', config('ai.rewrite_driver'), ['stub', 'gemini', 'claude']),
        });
    }

    /**
     * A name nobody recognises is a mistake, and it has to be loud.
     *
     * These used to fall through to the stub. AI_REWRITE_DRIVER defaults to
     * whatever AI_DRIVER is, so going live on Gemini selected a rewriter that did
     * not exist yet and landed on `default` — canned copy served from a button
     * that looked live, with nothing logged and nothing failed. You would have
     * found out from a customer.
     *
     * Failing here means a bad value surfaces on the first request after the
     * deploy, naming itself.
     *
     * @param  array<int,string>  $allowed
     */
    private function reject(string $setting, mixed $given, array $allowed): never
    {
        throw new RuntimeException(sprintf(
            '%s is set to "%s", which is not a driver. Use one of: %s.',
            $setting,
            is_string($given) ? $given : var_export($given, true),
            implode(', ', $allowed),
        ));
    }

    public function boot(): void
    {
        //
    }
}
