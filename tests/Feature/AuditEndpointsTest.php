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
