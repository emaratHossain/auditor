<?php

namespace Tests\Feature;

use App\Jobs\AnalyzePageJob;
use App\Models\Audit;
use App\Models\Page;
use App\Services\Ai\AnalysisResult;
use App\Services\Ai\VisionAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Findings arrive named by the model and have to be put back on the section they
 * are about. A finding with no section carries no number, and the evidence
 * guarantee drops it — so a mismatch here empties the report without any error
 * being raised anywhere.
 *
 * This is what a real Gemini reply did on arraytics.com: three of four findings
 * had names the capture never used, and the report came back saying nothing
 * could be proven about a page the model had described accurately.
 */
class FindingsLandOnSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function auditWithSections(array $names): Audit
    {
        $page = Page::create(['name' => 'Matching test', 'url' => 'https://example.com/matching']);
        $audit = $page->audits()->create(['status' => Audit::STATUS_RUNNING]);

        foreach ($names as $i => $name) {
            $audit->sections()->create([
                'section_name'    => $name,
                'viewport'        => 'desktop',
                'screenshot_path' => "screenshots/{$audit->id}/{$i}.webp",
                'position'        => $i * 900,
                'height'          => 900,
                'page_height'     => 4200,
                'sort_order'      => $i,
            ]);
        }

        return $audit->fresh();
    }

    private function analyzerReturning(array $sectionNames): void
    {
        $sections = array_map(fn ($name) => [
            'section'  => $name,
            'score'    => 40,
            'problems' => [[
                'what'     => 'The main button is the same tone as the panel behind it.',
                'why'      => 'Nothing on the screen reads as the thing to press.',
                'fix'      => 'Raise the button contrast to at least 4.5:1.',
                'severity' => 4,
                'category' => 'cta',
            ]],
        ], $sectionNames);

        $this->app->bind(VisionAnalyzer::class, fn () => new class($sections) implements VisionAnalyzer
        {
            public function __construct(private array $sections) {}

            public function analyse(Audit $audit): AnalysisResult
            {
                return new AnalysisResult($this->sections, 'fake-model', 100, 0.001, ['sections' => $this->sections]);
            }

            public function modelName(): string
            {
                return 'fake-model';
            }
        });
    }

    public function test_a_name_written_out_in_full_still_lands_on_its_truncated_section(): void
    {
        $audit = $this->auditWithSections(['We Provide Tech Solutions & Help You to', 'Our Contributions']);
        $this->analyzerReturning(['We Provide Tech Solutions & Help You to Grow Your Business']);

        (new AnalyzePageJob($audit->id))->handle(app(VisionAnalyzer::class), app(\App\Services\Ai\AuditSchema::class));

        $finding = $audit->findings()->first();
        $expected = $audit->sections()->where('section_name', 'We Provide Tech Solutions & Help You to')->first();

        $this->assertSame($expected->id, $finding->screenshot_section_id);
    }

    public function test_a_name_that_differs_only_in_case_still_lands(): void
    {
        $audit = $this->auditWithSections(['Our Contributions']);
        $this->analyzerReturning(['our contributions']);

        (new AnalyzePageJob($audit->id))->handle(app(VisionAnalyzer::class), app(\App\Services\Ai\AuditSchema::class));

        $this->assertNotNull($audit->findings()->first()->screenshot_section_id);
    }

    public function test_an_invented_name_is_left_unlinked_rather_than_guessed_onto_a_section(): void
    {
        $audit = $this->auditWithSections(['Section 1', 'Our Contributions']);
        $this->analyzerReturning(['Testimonial Carousel']);

        (new AnalyzePageJob($audit->id))->handle(app(VisionAnalyzer::class), app(\App\Services\Ai\AuditSchema::class));

        $this->assertNull(
            $audit->findings()->first()->screenshot_section_id,
            'Guessing would put a real number beside the wrong part of the page.',
        );
    }

    public function test_it_records_how_many_findings_could_not_be_placed(): void
    {
        $audit = $this->auditWithSections(['Section 1', 'Our Contributions']);
        $this->analyzerReturning(['Section 1', 'Hero Section', 'Product Showcase']);

        (new AnalyzePageJob($audit->id))->handle(app(VisionAnalyzer::class), app(\App\Services\Ai\AuditSchema::class));

        $this->assertSame(2, $audit->fresh()->unmatched_findings);
    }
}
