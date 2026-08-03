<?php

namespace Tests\Feature;

use App\Models\Audit;
use Database\Seeders\DemoAuditSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeded audit is the insurance policy for demo day. If it needs a network,
 * a reachable URL and Chromium all behaving at once, it is not insurance.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_produces_a_finished_audit(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $audit = Audit::latest('id')->first();

        $this->assertSame(Audit::STATUS_COMPLETED, $audit->status);
        $this->assertIsInt($audit->overall_score);
    }

    public function test_the_seeded_numbers_are_the_fixture_numbers(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $metrics = Audit::latest('id')->first()->metrics;

        $this->assertSame(config('demo-analytics.visitors'), $metrics->visitors);
        $this->assertSame('demo', $metrics->source);
        $this->assertNotNull($metrics->rage_clicks);
    }

    public function test_the_sections_carry_the_pages_words(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $hero = Audit::latest('id')->first()->sections()->where('viewport', 'desktop')->first();

        $this->assertNotNull($hero->copy['headline']['text'] ?? null);
        $this->assertNotEmpty($hero->copy['ctas'] ?? []);
    }

    /** This is the whole wifi insurance: the rewrites exist before anyone clicks. */
    public function test_the_rewrites_are_already_stored(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $rewrites = Audit::latest('id')->first()->rewrites;

        $this->assertGreaterThanOrEqual(2, $rewrites->count());
        $this->assertGreaterThanOrEqual(2, count($rewrites->first()->variants));
        $this->assertNotEmpty($rewrites->first()->variants[0]['reason']);
    }

    public function test_lighthouse_scores_are_present_so_the_breakdown_says_measured(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $audit = Audit::latest('id')->first();

        $this->assertIsInt($audit->lighthouse['performance']);
        $this->assertIsInt($audit->lighthouse['accessibility']);
        $this->assertTrue($audit->category_scores['accessibility']['measured']);
    }

    public function test_the_fifth_rule_fired(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $this->assertTrue(
            Audit::latest('id')->first()->insights->contains('rule_key', 'rage_click_mismatch'),
            'the seeded numbers must demonstrate the rage-click rule on stage',
        );
    }

    /** Opening the seeded report must need nothing but the database. */
    public function test_the_whole_report_renders_from_the_seed_alone(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $id = Audit::latest('id')->first()->id;

        $this->getJson("/api/audits/{$id}/report")
            ->assertOk()
            ->assertJsonPath('data.metrics_source.key', 'demo')
            ->assertJsonStructure(['data' => ['rewrites', 'lighthouse', 'sections', 'recommendations']]);
    }
}
