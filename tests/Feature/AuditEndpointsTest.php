<?php

namespace Tests\Feature;

use App\Jobs\CaptureScreenshotsJob;
use App\Models\Audit;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * The seam between the two halves of the app. If these change shape the front
 * end breaks silently, so they are pinned down here.
 */
class AuditEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_page_is_created_and_then_listed(): void
    {
        $this->postJson('/api/pages', ['name' => 'Spring campaign', 'url' => 'https://example.com/spring'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Spring campaign');

        $this->getJson('/api/pages')
            ->assertOk()
            ->assertJsonPath('data.0.url', 'https://example.com/spring')
            ->assertJsonPath('data.0.latest_audit', null);
    }

    public function test_an_invalid_address_comes_back_with_a_message_for_that_field(): void
    {
        $this->postJson('/api/pages', ['name' => 'Broken', 'url' => 'not-a-url'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_starting_an_audit_returns_immediately_and_queues_the_four_stages(): void
    {
        Bus::fake();
        $page = Page::create(['name' => 'P', 'url' => 'https://example.com']);

        $this->postJson("/api/pages/{$page->id}/audits", [
            'visitors' => 1000, 'bounce_rate' => 60, 'conversion_rate' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', Audit::STATUS_PENDING);

        Bus::assertChained([CaptureScreenshotsJob::class, \App\Jobs\AnalyzePageJob::class, \App\Jobs\CorrelateJob::class, \App\Jobs\RankAndScoreJob::class]);
    }

    public function test_only_the_three_required_numbers_are_needed(): void
    {
        Bus::fake();
        $page = Page::create(['name' => 'P', 'url' => 'https://example.com']);

        $this->postJson("/api/pages/{$page->id}/audits", [
            'visitors' => 500, 'bounce_rate' => 50, 'conversion_rate' => 1,
        ])->assertCreated();

        $metrics = Audit::first()->metrics;

        $this->assertNull($metrics->cta_click_rate, 'A field the user left blank must stay blank, never default to a number.');
        $this->assertNull($metrics->mobile_bounce_rate);
    }

    public function test_it_accepts_the_clarity_numbers_and_remembers_they_were_demo_data(): void
    {
        Bus::fake();
        $page = Page::create(['name' => 'Pre-fill test', 'url' => 'https://example.com/pre-fill']);

        $response = $this->postJson("/api/pages/{$page->id}/audits", [
            'visitors'        => 18450,
            'bounce_rate'     => 64.2,
            'conversion_rate' => 1.8,
            'rage_clicks'     => ['Features' => 340],
            'dead_clicks'     => ['Features' => 512],
            'source'          => 'demo',
        ]);

        $response->assertCreated();

        $metrics = Audit::find($response->json('data.id'))->metrics;

        $this->assertSame(['Features' => 340], $metrics->rage_clicks);
        $this->assertSame(['Features' => 512], $metrics->dead_clicks);
        $this->assertSame('demo', $metrics->source);
    }

    public function test_the_source_defaults_to_manual_when_nobody_says_otherwise(): void
    {
        Bus::fake();
        $page = Page::create(['name' => 'Manual test', 'url' => 'https://example.com/manual']);

        $response = $this->postJson("/api/pages/{$page->id}/audits", [
            'visitors'        => 1000,
            'bounce_rate'     => 50.0,
            'conversion_rate' => 2.0,
        ]);

        $response->assertCreated();

        $this->assertSame('manual', Audit::find($response->json('data.id'))->metrics->source);
    }

    public function test_an_invented_source_is_rejected(): void
    {
        $page = Page::create(['name' => 'Bad source', 'url' => 'https://example.com/bad-source']);

        $this->postJson("/api/pages/{$page->id}/audits", [
            'visitors'        => 1000,
            'bounce_rate'     => 50.0,
            'conversion_rate' => 2.0,
            'source'          => 'guessed',
        ])->assertStatus(422)->assertJsonValidationErrors('source');
    }

    public function test_the_report_says_where_its_numbers_came_from(): void
    {
        $audit = $this->completedAudit();
        $audit->metrics->update(['source' => 'demo']);

        $this->getJson("/api/audits/{$audit->id}/report")
            ->assertOk()
            ->assertJsonPath('data.metrics_source.key', 'demo')
            ->assertJsonPath('data.metrics_source.label', config('demo-analytics.label'));
    }

    public function test_the_report_carries_the_pages_own_words(): void
    {
        $page = Page::create(['name' => 'Copy test', 'url' => 'https://example.com/copy']);
        $audit = $page->audits()->create(['status' => Audit::STATUS_COMPLETED]);

        $audit->sections()->create([
            'section_name'    => 'Hero',
            'viewport'        => 'desktop',
            'screenshot_path' => 'screenshots/1/hero-0-desktop.webp',
            'position'        => 0,
            'height'          => 900,
            'page_height'     => 4500,
            'sort_order'      => 0,
            'copy'            => [
                'headline' => ['text' => 'Ship faster', 'tag' => 'h1', 'selector' => 'h1'],
                'subhead'  => ['text' => 'One dashboard.', 'tag' => 'p', 'selector' => 'p:nth-child(2)'],
                'ctas'     => [['text' => 'Start free trial', 'tag' => 'a', 'selector' => 'a.btn']],
            ],
        ]);

        $this->getJson("/api/audits/{$audit->id}/report")
            ->assertOk()
            ->assertJsonPath('data.sections.0.copy.headline.text', 'Ship faster')
            ->assertJsonPath('data.sections.0.copy.ctas.0.text', 'Start free trial');
    }

    public function test_a_section_with_no_readable_copy_returns_null_not_an_empty_shell(): void
    {
        $page = Page::create(['name' => 'No copy', 'url' => 'https://example.com/no-copy']);
        $audit = $page->audits()->create(['status' => Audit::STATUS_COMPLETED]);

        $audit->sections()->create([
            'section_name'    => 'Hero',
            'viewport'        => 'desktop',
            'screenshot_path' => 'screenshots/2/hero-0-desktop.webp',
            'position'        => 0,
            'height'          => 900,
            'page_height'     => 4500,
            'sort_order'      => 0,
        ]);

        $this->getJson("/api/audits/{$audit->id}/report")
            ->assertOk()
            ->assertJsonPath('data.sections.0.copy', null);
    }

    public function test_a_bounce_rate_of_one_hundred_and_fifty_is_rejected(): void
    {
        $page = Page::create(['name' => 'P', 'url' => 'https://example.com']);

        $this->postJson("/api/pages/{$page->id}/audits", [
            'visitors' => 500, 'bounce_rate' => 150, 'conversion_rate' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('bounce_rate');
    }

    public function test_polling_a_running_audit_reports_the_stage_in_plain_words(): void
    {
        $page = Page::create(['name' => 'P', 'url' => 'https://example.com']);
        $audit = $page->audits()->create(['status' => Audit::STATUS_RUNNING, 'stage' => 'analysing']);

        $this->getJson("/api/audits/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.stage', 'analysing')
            ->assertJsonPath('data.stage_label', 'The AI is looking at each section');
    }

    public function test_the_report_returns_everything_in_one_response(): void
    {
        $audit = $this->completedAudit();

        $this->getJson("/api/audits/{$audit->id}/report")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'status', 'page' => ['name', 'url'],
                'score' => ['overall', 'categories'],
                'metrics', 'sections', 'recommendations', 'insights', 'cost',
            ]]);
    }

    public function test_screenshot_links_are_signed_and_never_a_raw_disk_path(): void
    {
        $audit = $this->completedAudit();

        $url = $this->getJson("/api/audits/{$audit->id}/report")->json('data.sections.0.screenshot_url');

        $this->assertStringContainsString('signature=', $url, 'A leaked link must not expose a client page forever.');
        $this->assertStringNotContainsString('storage/app', $url);
    }

    public function test_an_unknown_audit_returns_not_found(): void
    {
        $this->getJson('/api/audits/99999/report')->assertNotFound();
    }

    public function test_every_recommendation_carries_a_number_as_evidence(): void
    {
        $audit = $this->completedAudit();

        $recommendations = $this->getJson("/api/audits/{$audit->id}/report")->json('data.recommendations');

        $this->assertNotEmpty($recommendations);

        foreach ($recommendations as $rec) {
            $this->assertMatchesRegularExpression(
                '/\d/', $rec['evidence'],
                'The evidence guarantee means every fix on screen must point at an actual number.'
            );
        }
    }

    /** Runs the real pipeline against the stub drivers — no network, no browser. */
    private function completedAudit(): Audit
    {
        $page = Page::create(['name' => 'Demo', 'url' => 'https://example.com/demo']);

        $audit = app(\App\Services\AuditService::class)->start($page, [
            'visitors' => 12000, 'bounce_rate' => 64, 'conversion_rate' => 1.7,
            'cta_click_rate' => 2.1, 'mobile_share' => 70, 'mobile_bounce_rate' => 81,
            'section_reach' => ['Hero' => 96, 'Pricing' => 19],
        ]);

        return $audit->fresh();
    }
}
