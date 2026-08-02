<?php

namespace Tests\Unit\Correlation;

use App\Services\Correlation\EvidenceGuarantee;
use App\Services\Correlation\Support\InsightCandidate;
use PHPUnit\Framework\TestCase;

/**
 * An insight that cannot name a metric, a number and a section is discarded —
 * not downgraded, not shown with a caveat. Discarded.
 *
 * This is the single rule that stops the tool degenerating into a generic
 * design-tips generator, so it gets its own test rather than being folded into
 * the rule tests.
 */
class EvidenceGuaranteeTest extends TestCase
{
    private function candidate(array $evidence, string $sectionName = 'Hero'): InsightCandidate
    {
        return new InsightCandidate(
            ruleKey: 'seen_but_not_clicked',
            sectionName: $sectionName,
            statement: 'People find the button and ignore it.',
            evidence: $evidence,
            confidence: 0.9,
            severity: 4,
        );
    }

    public function test_an_insight_with_a_metric_a_number_and_a_section_survives(): void
    {
        $kept = (new EvidenceGuarantee)->filter([
            $this->candidate(['metric' => 'cta_click_rate', 'value' => 2.0, 'unit' => '%']),
        ]);

        $this->assertCount(1, $kept);
    }

    public function test_an_insight_missing_the_number_is_discarded(): void
    {
        $kept = (new EvidenceGuarantee)->filter([
            $this->candidate(['metric' => 'cta_click_rate', 'unit' => '%']),
        ]);

        $this->assertSame([], $kept, 'No number means no evidence, so it must not reach the user.');
    }

    public function test_an_insight_missing_the_metric_name_is_discarded(): void
    {
        $kept = (new EvidenceGuarantee)->filter([
            $this->candidate(['value' => 2.0, 'unit' => '%']),
        ]);

        $this->assertSame([], $kept);
    }

    public function test_an_insight_missing_the_section_name_is_discarded(): void
    {
        $kept = (new EvidenceGuarantee)->filter([
            $this->candidate(['metric' => 'cta_click_rate', 'value' => 2.0], sectionName: '   '),
        ]);

        $this->assertSame([], $kept);
    }

    public function test_a_non_numeric_value_does_not_count_as_a_number(): void
    {
        $kept = (new EvidenceGuarantee)->filter([
            $this->candidate(['metric' => 'cta_click_rate', 'value' => 'quite low', 'unit' => '%']),
        ]);

        $this->assertSame([], $kept, '"quite low" is an opinion wearing a number.');
    }

    public function test_a_run_where_nothing_can_prove_itself_returns_an_empty_list_not_a_placeholder(): void
    {
        $kept = (new EvidenceGuarantee)->filter([
            $this->candidate([]),
            $this->candidate(['metric' => 'bounce_rate']),
        ]);

        $this->assertSame([], $kept, 'Showing nothing is correct. Inventing a placeholder is not.');
    }

    public function test_it_keeps_the_good_and_drops_the_bad_in_the_same_run(): void
    {
        $kept = (new EvidenceGuarantee)->filter([
            $this->candidate(['metric' => 'bounce_rate', 'value' => 71.0]),
            $this->candidate([]),
            $this->candidate(['metric' => 'cta_click_rate', 'value' => 2.0]),
        ]);

        $this->assertCount(2, $kept);
    }

    public function test_zero_is_a_real_number_and_must_not_be_treated_as_missing(): void
    {
        $kept = (new EvidenceGuarantee)->filter([
            $this->candidate(['metric' => 'cta_click_rate', 'value' => 0.0, 'unit' => '%']),
        ]);

        $this->assertCount(1, $kept, 'A click rate of exactly zero is the strongest evidence there is.');
    }
}
