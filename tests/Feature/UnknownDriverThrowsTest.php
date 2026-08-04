<?php

namespace Tests\Feature;

use App\Services\Ai\GeminiVisionAnalyzer;
use App\Services\Ai\StubVisionAnalyzer;
use App\Services\Ai\VisionAnalyzer;
use App\Services\Rewrite\CopyRewriter;
use App\Services\Rewrite\GeminiCopyRewriter;
use App\Services\Rewrite\StubCopyRewriter;
use RuntimeException;
use Tests\TestCase;

/**
 * A name the container does not recognise must not quietly become the stub.
 *
 * `rewrite_driver` defaults to whatever `AI_DRIVER` is, so setting AI_DRIVER=gemini
 * and nothing else used to select a rewriter that did not exist — and land on
 * `default`, serving canned copy from a button that looked live. Nothing was
 * logged and nothing failed. You would find out from a customer.
 *
 * Failing at the container is the whole point: it happens on the first request
 * after a bad deploy, with the offending value in the message, rather than
 * silently for as long as nobody looks closely at the output.
 */
class UnknownDriverThrowsTest extends TestCase
{
    public function test_an_unknown_vision_driver_is_refused_by_name(): void
    {
        config(['ai.driver' => 'gemeni']);   // the typo that started this

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/gemeni/');

        app(VisionAnalyzer::class);
    }

    public function test_an_unknown_rewrite_driver_is_refused_by_name(): void
    {
        config(['ai.rewrite_driver' => 'sonnet']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sonnet/');

        app(CopyRewriter::class);
    }

    public function test_the_error_lists_what_you_could_have_meant(): void
    {
        config(['ai.driver' => 'nonsense']);

        try {
            app(VisionAnalyzer::class);
            $this->fail('An unknown driver must not resolve.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('stub', $e->getMessage());
            $this->assertStringContainsString('gemini', $e->getMessage());
            $this->assertStringContainsString('claude', $e->getMessage());
        }
    }

    /** @dataProvider validDrivers */
    public function test_every_real_driver_still_resolves(string $key, string $driver, string $abstract, string $expected): void
    {
        config([$key => $driver]);

        $this->assertInstanceOf($expected, app($abstract));
    }

    public static function validDrivers(): array
    {
        return [
            'vision stub'    => ['ai.driver', 'stub', VisionAnalyzer::class, StubVisionAnalyzer::class],
            'vision gemini'  => ['ai.driver', 'gemini', VisionAnalyzer::class, GeminiVisionAnalyzer::class],
            'rewrite stub'   => ['ai.rewrite_driver', 'stub', CopyRewriter::class, StubCopyRewriter::class],
            'rewrite gemini' => ['ai.rewrite_driver', 'gemini', CopyRewriter::class, GeminiCopyRewriter::class],
        ];
    }
}
