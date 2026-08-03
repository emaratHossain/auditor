<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A report must never present invented findings about a real URL as fact.
 *
 * With the stub drivers on, nothing opens a browser and no model is called: the
 * screenshots are placeholders and the critique is canned. That is a legitimate
 * mode — it is how the whole suite runs and how the demo survives a dead
 * network — but the screen has to say so, exactly as it already says when the
 * numbers are demo numbers.
 */
class SimulatedAuditTest extends TestCase
{
    use RefreshDatabase;

    private function audit(string $captureDriver, string $aiModel): Audit
    {
        $page = Page::create(['name' => 'Sim test', 'url' => 'https://example.com/sim']);

        return $page->audits()->create([
            'status'         => Audit::STATUS_COMPLETED,
            'capture_driver' => $captureDriver,
            'ai_model'       => $aiModel,
        ]);
    }

    public function test_a_fully_simulated_audit_says_the_page_was_never_visited(): void
    {
        $audit = $this->audit('stub', 'stub');

        $response = $this->getJson("/api/audits/{$audit->id}/report")->assertOk();

        $this->assertTrue($response->json('data.simulated.any'));
        $this->assertFalse($response->json('data.simulated.page_visited'));
        $this->assertFalse($response->json('data.simulated.ai_analysed'));
    }

    public function test_a_real_audit_is_not_labelled(): void
    {
        $audit = $this->audit('playwright', 'claude-sonnet-5');

        $response = $this->getJson("/api/audits/{$audit->id}/report")->assertOk();

        $this->assertFalse($response->json('data.simulated.any'));
        $this->assertTrue($response->json('data.simulated.page_visited'));
        $this->assertTrue($response->json('data.simulated.ai_analysed'));
    }

    /** Half real is still not real, and the half that is invented is the half that matters. */
    public function test_a_real_capture_with_a_stubbed_model_is_still_flagged(): void
    {
        $audit = $this->audit('playwright', 'stub');

        $response = $this->getJson("/api/audits/{$audit->id}/report")->assertOk();

        $this->assertTrue($response->json('data.simulated.any'));
        $this->assertTrue($response->json('data.simulated.page_visited'));
        $this->assertFalse($response->json('data.simulated.ai_analysed'));
    }

    /** Older audits predate the column and must not crash the report. */
    public function test_an_audit_with_no_recorded_driver_does_not_break_the_report(): void
    {
        $audit = $this->audit('stub', 'stub');
        $audit->forceFill(['capture_driver' => null])->save();

        $this->getJson("/api/audits/{$audit->id}/report")
            ->assertOk()
            ->assertJsonStructure(['data' => ['simulated' => ['any', 'page_visited', 'ai_analysed', 'note']]]);
    }

    public function test_the_note_names_what_was_invented(): void
    {
        $audit = $this->audit('stub', 'stub');

        $note = $this->getJson("/api/audits/{$audit->id}/report")->json('data.simulated.note');

        $this->assertStringContainsString('not visited', $note);
        $this->assertStringContainsString('CAPTURE_DRIVER', $note);
    }
}
