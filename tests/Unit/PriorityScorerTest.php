<?php

namespace Tests\Unit;

use App\Services\PriorityScorer;
use PHPUnit\Framework\TestCase;

/**
 * priority = (traffic share x severity x confidence) / effort
 *
 * A score rather than a feeling, so two people reading the same audit agree on
 * what to do first.
 */
class PriorityScorerTest extends TestCase
{
    public function test_a_small_problem_on_a_busy_section_beats_a_big_problem_almost_nobody_sees(): void
    {
        $scorer = new PriorityScorer;

        $smallProblemEveryoneSees = $scorer->score(trafficShare: 0.90, severity: 2, confidence: 0.9, effort: 2);
        $bigProblemNobodyReaches  = $scorer->score(trafficShare: 0.05, severity: 5, confidence: 0.9, effort: 2);

        $this->assertGreaterThan(
            $bigProblemNobodyReaches,
            $smallProblemEveryoneSees,
            'Reach has to dominate raw severity, or the tool sends people to fix the wrong thing.'
        );
    }

    public function test_a_missing_section_reach_falls_back_to_everyone_not_to_nobody(): void
    {
        $scorer = new PriorityScorer;

        $this->assertSame(
            $scorer->score(trafficShare: 1.0, severity: 4, confidence: 0.8, effort: 2),
            $scorer->score(trafficShare: null, severity: 4, confidence: 0.8, effort: 2),
            'When reach is unknown, assuming nobody sees the section would silently bury real problems.'
        );
    }

    public function test_less_work_for_the_same_result_ranks_higher(): void
    {
        $scorer = new PriorityScorer;

        $this->assertGreaterThan(
            $scorer->score(trafficShare: 0.8, severity: 4, confidence: 0.9, effort: 5),
            $scorer->score(trafficShare: 0.8, severity: 4, confidence: 0.9, effort: 1),
        );
    }

    public function test_shakier_evidence_ranks_lower_than_solid_evidence(): void
    {
        $scorer = new PriorityScorer;

        $this->assertGreaterThan(
            $scorer->score(trafficShare: 0.8, severity: 4, confidence: 0.4, effort: 2),
            $scorer->score(trafficShare: 0.8, severity: 4, confidence: 1.0, effort: 2),
        );
    }

    public function test_there_are_exactly_three_buckets_and_never_a_fourth(): void
    {
        $scorer = new PriorityScorer;

        $buckets = [];
        foreach (range(0, 50) as $step) {
            $buckets[] = $scorer->bucket($step / 10);
        }

        $this->assertSame(['high', 'low', 'medium'], collect($buckets)->unique()->sort()->values()->all());
    }

    public function test_the_worst_possible_problem_is_high_and_the_mildest_is_low(): void
    {
        $scorer = new PriorityScorer;

        $this->assertSame('high', $scorer->bucket($scorer->score(trafficShare: 1.0, severity: 5, confidence: 1.0, effort: 1)));
        $this->assertSame('low',  $scorer->bucket($scorer->score(trafficShare: 0.02, severity: 1, confidence: 0.3, effort: 5)));
    }

    public function test_effort_of_zero_does_not_blow_up(): void
    {
        $this->assertIsFloat((new PriorityScorer)->score(trafficShare: 0.5, severity: 3, confidence: 0.8, effort: 0));
    }
}
