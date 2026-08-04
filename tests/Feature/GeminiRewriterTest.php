<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\Rewrite\GeminiCopyRewriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Going live on Gemini alone left F11 with no real driver at all: the container
 * had 'claude' and a default, so AI_REWRITE_DRIVER=gemini fell through to the
 * stub and every "Rewrite this" button served canned copy while looking live.
 *
 * Same call as the vision pass, different schema.
 */
class GeminiRewriterTest extends TestCase
{
    use RefreshDatabase;

    private function audit(): \App\Models\Audit
    {
        $page = Page::create(['name' => 'Rewrite test', 'url' => 'https://example.com/rewrite']);

        return $page->audits()->create(['status' => 'completed']);
    }

    private function fakeReply(array $variants, array $usage = []): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['variants' => $variants])]]],
                ]],
                'usageMetadata' => $usage ?: ['promptTokenCount' => 200, 'candidatesTokenCount' => 300],
            ]),
        ]);
    }

    private function rewrite(): \App\Services\Rewrite\RewriteResult
    {
        return (new GeminiCopyRewriter)->rewrite(
            $this->audit(),
            'Hero',
            'headline',
            'We Provide Tech Solutions',
            'The headline states a feature rather than an outcome.',
            'Only 2.1% press the main button.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.gemini.key' => 'test-key', 'ai.gemini.model' => 'gemini-3.6-flash']);
    }

    public function test_it_returns_the_versions_the_model_wrote(): void
    {
        $this->fakeReply([
            ['text' => 'Grow your business with tools that work', 'reason' => 'States the outcome, not the category.'],
            ['text' => 'Ship faster with plugins your team already knows', 'reason' => 'Names the benefit in the reader\'s words.'],
        ]);

        $result = $this->rewrite();

        $this->assertCount(2, $result->variants);
        $this->assertSame('Grow your business with tools that work', $result->variants[0]['text']);
        $this->assertSame('gemini-3.6-flash', $result->model);
    }

    public function test_thinking_tokens_are_counted_here_too(): void
    {
        $this->fakeReply(
            [['text' => 'A', 'reason' => 'B'], ['text' => 'C', 'reason' => 'D']],
            ['promptTokenCount' => 200, 'candidatesTokenCount' => 300, 'thoughtsTokenCount' => 900],
        );

        $this->assertSame(1_400, $this->rewrite()->tokens);
    }

    public function test_a_missing_key_says_what_to_do_about_it(): void
    {
        config(['ai.gemini.key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/GEMINI_API_KEY/');

        $this->rewrite();
    }

    public function test_a_refused_request_is_not_mistaken_for_an_empty_rewrite(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/429|quota/');

        $this->rewrite();
    }

    public function test_a_reply_that_is_not_json_throws_rather_than_returning_nothing(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Sorry, I cannot help with that.']]]]],
            ]),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->rewrite();
    }
}
