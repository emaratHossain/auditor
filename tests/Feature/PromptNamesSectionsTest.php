<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\Ai\PromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A finding is only worth anything if we know which section it is about.
 *
 * Left to itself a vision model names sections after what it sees — "Hero
 * Section", "Product Showcase" — while the capture named them after the heading
 * it found in the HTML. Nothing then lines up, every finding loses its section,
 * and the evidence guarantee correctly discards the lot. The prompt has to hand
 * over the real names and ask for them back.
 */
class PromptNamesSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function instruction(): string
    {
        $page = Page::create(['name' => 'Prompt test', 'url' => 'https://example.com/prompt']);
        $audit = $page->audits()->create(['status' => 'running']);

        foreach ([['Section 1', 'desktop'], ['Our Contributions', 'desktop'], ['Section 1', 'mobile']] as $i => [$name, $viewport]) {
            $audit->sections()->create([
                'section_name'    => $name,
                'viewport'        => $viewport,
                'screenshot_path' => "screenshots/{$audit->id}/{$i}.webp",
                'position'        => $i * 900,
                'height'          => 900,
                'page_height'     => 4200,
                'sort_order'      => $i,
            ]);
        }

        return (new PromptBuilder)->instruction($audit->fresh());
    }

    public function test_the_prompt_lists_every_captured_desktop_section_by_name(): void
    {
        $instruction = $this->instruction();

        $this->assertStringContainsString('Section 1', $instruction);
        $this->assertStringContainsString('Our Contributions', $instruction);
    }

    public function test_the_prompt_requires_the_names_to_be_copied_rather_than_invented(): void
    {
        $instruction = $this->instruction();

        $this->assertMatchesRegularExpression(
            '/copy .*exactly|exactly as written|word for word/i',
            $instruction,
            'The model must be told to reuse the given names, not describe the section in its own words.',
        );
        $this->assertMatchesRegularExpression(
            '/do not invent|must not invent|never invent/i',
            $instruction,
            'Inventing a section name is the specific failure this instruction exists to prevent.',
        );
    }

    public function test_it_tells_the_model_where_to_put_a_phone_only_problem(): void
    {
        // The mobile shot has no section of its own, so a model with something to
        // say about phones invents one ("Mobile Experience") and the finding is
        // lost. It has to go on the desktop section it belongs to.
        $instruction = $this->instruction();

        $this->assertMatchesRegularExpression(
            '/(phone|mobile)[^.]{0,120}(section it|one of the sections|section list|listed section)/i',
            $instruction,
            'A phone-only problem needs an explicit home, or the model will invent a section for it.',
        );
        $this->assertStringNotContainsString(
            'Mobile Experience',
            $instruction,
            'The prompt must not itself suggest a section name that was never captured.',
        );
    }
}
