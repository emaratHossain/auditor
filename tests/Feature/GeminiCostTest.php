<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\Ai\GeminiVisionAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gemini's thinking tokens are billed as output but reported apart from it, in
 * usageMetadata.thoughtsTokenCount. Counting only candidatesTokenCount reports a
 * fraction of what the audit really cost — and, worse, the ceiling in
 * AnalyzePageJob is checked against that same understated figure, so the guard
 * meant to stop a runaway bill never trips.
 *
 * On a 3.x flash model the thinking tokens routinely outnumber the visible reply
 * several times over, so this is not a rounding argument.
 */
class GeminiCostTest extends TestCase
{
    use RefreshDatabase;

    private function analyse(array $usage): \App\Services\Ai\AnalysisResult
    {
        config(['ai.gemini.key' => 'test-key', 'ai.gemini.model' => 'gemini-3.6-flash']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['sections' => [[
                            'section'  => 'Section 1',
                            'score'    => 50,
                            'problems' => [[
                                'what' => 'The button is low contrast.',
                                'why' => 'Nothing reads as clickable.',
                                'fix' => 'Raise contrast to 4.5:1.',
                                'severity' => 4,
                                'category' => 'cta',
                            ]],
                        ]]]),
                    ]]],
                ]],
                'usageMetadata' => $usage,
            ]),
        ]);

        $page = Page::create(['name' => 'Cost test', 'url' => 'https://example.com/cost']);
        $audit = $page->audits()->create(['status' => 'running']);

        return (new GeminiVisionAnalyzer)->analyse($audit->fresh());
    }

    public function test_thinking_tokens_are_counted_as_the_output_they_are_billed_as(): void
    {
        $result = $this->analyse([
            'promptTokenCount'     => 1_000,
            'candidatesTokenCount' => 500,
            'thoughtsTokenCount'   => 4_000,
        ]);

        $this->assertSame(5_500, $result->tokens, 'Every token the reply was billed for must be counted.');
    }

    public function test_the_recorded_cost_charges_thinking_at_the_output_rate(): void
    {
        $result = $this->analyse([
            'promptTokenCount'     => 1_000,
            'candidatesTokenCount' => 500,
            'thoughtsTokenCount'   => 4_000,
        ]);

        // 1,000 in + 4,500 out, at the configured per-million rates.
        $expected = round(
            (1_000 / 1_000_000 * config('ai.gemini.input_per_million'))
            + (4_500 / 1_000_000 * config('ai.gemini.output_per_million')),
            5,
        );

        $this->assertSame($expected, $result->cost);
    }

    public function test_a_model_that_reports_no_thinking_is_unaffected(): void
    {
        $result = $this->analyse([
            'promptTokenCount'     => 1_000,
            'candidatesTokenCount' => 500,
        ]);

        $this->assertSame(1_500, $result->tokens);
    }

    public function test_the_rates_are_configurable_so_a_new_model_can_be_priced_correctly(): void
    {
        // The published rate differs per model, and the constants were written for
        // gemini-2.5-flash. Anyone switching models must be able to correct them
        // without editing the analyser.
        config(['ai.gemini.input_per_million' => 1.0, 'ai.gemini.output_per_million' => 10.0]);

        $result = $this->analyse([
            'promptTokenCount'     => 1_000_000,
            'candidatesTokenCount' => 0,
            'thoughtsTokenCount'   => 1_000_000,
        ]);

        $this->assertSame(11.0, $result->cost);
    }
}
