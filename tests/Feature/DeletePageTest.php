<?php

namespace Tests\Feature;

use App\Models\AiFinding;
use App\Models\Audit;
use App\Models\Insight;
use App\Models\Page;
use App\Models\PageMetrics;
use App\Models\Recommendation;
use App\Models\Rewrite;
use App\Models\ScreenshotSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deleting a page.
 *
 * The list only ever grew: a page added by an e2e run, a URL typed wrong, a
 * campaign that ended. Deleting one has to take everything that belonged to it,
 * including the pictures — a screenshots directory that grows forever is how a
 * disk fills up quietly.
 *
 * The cascade runs inside SQLite, where Eloquent's deleting events never fire.
 * That is the reason file cleanup is explicit rather than an observer, and the
 * reason it is worth a test.
 */
class DeletePageTest extends TestCase
{
    use RefreshDatabase;

    private function pageWithAnAudit(string $name = 'Old campaign'): array
    {
        $page = Page::create(['name' => $name, 'url' => 'https://example.com/old']);

        $audit = $page->audits()->create([
            'status' => Audit::STATUS_COMPLETED,
            'overall_score' => 61,
        ]);

        PageMetrics::create([
            'audit_id' => $audit->id, 'visitors' => 1000,
            'bounce_rate' => 60, 'conversion_rate' => 2, 'source' => 'manual',
        ]);

        ScreenshotSection::create([
            'audit_id' => $audit->id, 'section_name' => 'Hero', 'viewport' => 'desktop',
            'screenshot_path' => "screenshots/{$audit->id}/hero-desktop.webp",
            'position' => 0, 'height' => 900, 'page_height' => 4000, 'sort_order' => 0,
        ]);

        AiFinding::create([
            'audit_id' => $audit->id, 'section_name' => 'Hero', 'ai_score' => 40,
            'problems' => ['The button is hard to find'],
        ]);

        $insight = Insight::create([
            'audit_id' => $audit->id, 'section_name' => 'Hero',
            'rule_key' => 'seen_but_not_clicked',
            'statement' => 'People reach the hero and do not press the button.',
            'evidence' => ['metric' => 'cta_click_rate', 'value' => 1.2],
            'confidence' => 0.8, 'severity' => 4,
        ]);

        Recommendation::create([
            'audit_id' => $audit->id, 'insight_id' => $insight->id, 'section_name' => 'Hero',
            'title' => 'Move the button up', 'evidence' => 'Only 1.2% press it.',
            'suggested_fix' => 'Put it above the fold.', 'expected_impact' => '+3-6% conversion',
            'priority' => 'high', 'priority_score' => 9.1, 'effort' => 2, 'severity' => 4,
            'traffic_share' => 0.8, 'confidence' => 0.8,
        ]);

        Rewrite::create([
            'audit_id' => $audit->id, 'section_name' => 'Hero', 'element' => 'headline',
            'original' => 'Welcome', 'variants' => ['Ship faster today'],
        ]);

        return [$page, $audit];
    }

    private function putFilesFor(Audit $audit): void
    {
        Storage::disk('public')->put("screenshots/{$audit->id}/hero-desktop.webp", 'not really a webp');
        Storage::disk('public')->put("screenshots/{$audit->id}/page-mobile.webp", 'not really a webp');

        $dir = storage_path('app/pdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents("{$dir}/audit-{$audit->id}.pdf", '%PDF-1.4');
        file_put_contents("{$dir}/audit-{$audit->id}.html", '<html></html>');
    }

    public function test_it_deletes_the_page(): void
    {
        [$page] = $this->pageWithAnAudit();

        $this->deleteJson("/api/pages/{$page->id}")->assertNoContent();

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_the_audits_and_everything_under_them_go_too(): void
    {
        [$page, $audit] = $this->pageWithAnAudit();

        $this->deleteJson("/api/pages/{$page->id}")->assertNoContent();

        $this->assertDatabaseMissing('audits', ['id' => $audit->id]);

        foreach ([
            'page_metrics', 'screenshot_sections', 'ai_findings',
            'insights', 'recommendations', 'rewrites',
        ] as $table) {
            $this->assertDatabaseMissing($table, ['audit_id' => $audit->id]);
        }
    }

    public function test_the_screenshots_and_the_pdf_go_with_it(): void
    {
        Storage::fake('public');
        [$page, $audit] = $this->pageWithAnAudit();
        $this->putFilesFor($audit);

        $this->deleteJson("/api/pages/{$page->id}")->assertNoContent();

        Storage::disk('public')->assertDirectoryEmpty("screenshots/{$audit->id}");
        $this->assertFileDoesNotExist(storage_path("app/pdf/audit-{$audit->id}.pdf"));
        $this->assertFileDoesNotExist(storage_path("app/pdf/audit-{$audit->id}.html"));
    }

    public function test_another_page_keeps_its_own_audits_and_pictures(): void
    {
        Storage::fake('public');
        [$doomed, $doomedAudit] = $this->pageWithAnAudit('Going');
        [$kept, $keptAudit]     = $this->pageWithAnAudit('Staying');
        $this->putFilesFor($doomedAudit);
        $this->putFilesFor($keptAudit);

        $this->deleteJson("/api/pages/{$doomed->id}")->assertNoContent();

        $this->assertDatabaseHas('pages', ['id' => $kept->id]);
        $this->assertDatabaseHas('audits', ['id' => $keptAudit->id]);
        Storage::disk('public')->assertExists("screenshots/{$keptAudit->id}/hero-desktop.webp");
        $this->assertFileExists(storage_path("app/pdf/audit-{$keptAudit->id}.pdf"));
    }

    public function test_a_page_that_was_never_audited_deletes_cleanly(): void
    {
        $page = Page::create(['name' => 'Never run', 'url' => 'https://example.com/new']);

        $this->deleteJson("/api/pages/{$page->id}")->assertNoContent();

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_an_id_that_is_not_there_is_a_404(): void
    {
        $this->deleteJson('/api/pages/999999')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_the_list_says_how_many_reports_a_page_has(): void
    {
        // The confirm dialog counts these, so the number has to be in the API.
        [$page] = $this->pageWithAnAudit();
        $page->audits()->create(['status' => Audit::STATUS_FAILED]);

        $this->getJson('/api/pages')
            ->assertOk()
            ->assertJsonPath('data.0.audits_count', 2);
    }
}
