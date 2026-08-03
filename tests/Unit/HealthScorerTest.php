<?php

namespace Tests\Unit;

use App\Services\HealthScorer;
use PHPUnit\Framework\TestCase;

/**
 * Six weighted categories rolled into one number out of 100. The weights are
 * visible in the app so nobody thinks the number is magic.
 */
class HealthScorerTest extends TestCase
{
    public function test_the_six_weights_add_up_to_one_hundred(): void
    {
        $this->assertSame(100, array_sum(HealthScorer::WEIGHTS));
    }

    public function test_the_overall_score_is_the_weighted_average_rounded(): void
    {
        $result = (new HealthScorer)->score([
            'cta'           => 60,
            'ux'            => 78,
            'ui'            => 84,
            'trust'         => 72,
            'performance'   => 91,
            'accessibility' => 90,
        ]);

        // 60*.25 + 78*.20 + 84*.20 + 72*.15 + 91*.10 + 90*.10 = 75.5 -> 76
        $this->assertSame(76, $result['overall']);
    }

    public function test_a_category_with_no_data_is_left_out_rather_than_scored_zero(): void
    {
        $scorer = new HealthScorer;

        $withEverythingPerfect = $scorer->score([
            'cta' => 80, 'ux' => 80, 'ui' => 80, 'trust' => 80, 'performance' => 80, 'accessibility' => 80,
        ]);

        $withSpeedUnknown = $scorer->score([
            'cta' => 80, 'ux' => 80, 'ui' => 80, 'trust' => 80, 'accessibility' => 80,
        ]);

        $this->assertSame(
            $withEverythingPerfect['overall'],
            $withSpeedUnknown['overall'],
            'A missing category must not silently drag the total down — that would punish the user for a number we failed to collect.'
        );
    }

    public function test_it_reports_the_breakdown_so_the_number_can_be_taken_apart(): void
    {
        $result = (new HealthScorer)->score(['cta' => 60, 'ux' => 78]);

        $this->assertSame(25, $result['categories']['cta']['weight']);
        $this->assertSame(60, $result['categories']['cta']['score']);
        $this->assertArrayHasKey('label', $result['categories']['cta']);
    }

    public function test_accessibility_is_labelled_as_an_estimate_not_an_audit(): void
    {
        $result = (new HealthScorer)->score(['accessibility' => 90]);

        $this->assertNotNull(
            $result['categories']['accessibility']['caveat'] ?? null,
            'Calling an AI guess an accessibility audit would be exactly the dishonesty this product exists to avoid.'
        );
    }

    public function test_an_audit_with_nothing_to_score_returns_null_rather_than_zero(): void
    {
        $this->assertNull((new HealthScorer)->score([])['overall']);
    }

    public function test_scores_are_clamped_to_the_zero_to_one_hundred_range(): void
    {
        $result = (new HealthScorer)->score(['cta' => 140, 'ux' => -20]);

        $this->assertSame(100, $result['categories']['cta']['score']);
        $this->assertSame(0, $result['categories']['ux']['score']);
    }

    public function test_a_measured_category_carries_no_caveat(): void
    {
        $result = (new HealthScorer)->score(
            ['performance' => 71, 'accessibility' => 88],
            measured: ['performance', 'accessibility'],
        );

        $this->assertTrue($result['categories']['accessibility']['measured']);
        $this->assertNull(
            $result['categories']['accessibility']['caveat'],
            'a measured score must not still claim to be an AI approximation',
        );
    }

    public function test_an_unmeasured_category_keeps_its_caveat(): void
    {
        $result = (new HealthScorer)->score(['accessibility' => 88]);

        $this->assertFalse($result['categories']['accessibility']['measured']);
        $this->assertStringContainsString('estimate', $result['categories']['accessibility']['caveat']);
    }

    public function test_the_weights_do_not_move_when_a_score_is_measured(): void
    {
        $scorer = new HealthScorer;

        $estimated = $scorer->score(['performance' => 60, 'accessibility' => 60]);
        $measured  = $scorer->score(['performance' => 60, 'accessibility' => 60], measured: ['performance', 'accessibility']);

        $this->assertSame($estimated['overall'], $measured['overall']);
    }

    public function test_a_dead_lighthouse_run_does_not_kill_the_audit(): void
    {
        // Exactly what RankAndScoreJob produces when audits.lighthouse is null:
        // the two categories fall back to the AI estimates and are labelled.
        $result = (new HealthScorer)->score([
            'cta' => 40, 'ux' => 50, 'ui' => 60, 'trust' => 70,
            'performance' => 55, 'accessibility' => 65,
        ]);

        $this->assertIsInt($result['overall']);
        $this->assertFalse($result['categories']['performance']['measured']);
        $this->assertFalse($result['categories']['accessibility']['measured']);
    }
}
