<?php

namespace Tests\Unit;

use App\Services\PriorityScorer;
use PHPUnit\Framework\TestCase;

/**
 * A buried section is a special case in the priority formula.
 *
 * Every other rule reports a problem affecting the people who DO reach a
 * section, so current reach is the right multiplier. This rule reports that
 * people are not reaching it at all — so multiplying by the reach it just
 * complained about punishes the finding for its own subject, and the more
 * badly buried a section is, the lower it ranks. Exactly backwards.
 *
 * The audience that matters here is the share being lost.
 */
class BuriedSectionPriorityTest extends TestCase
{
    public function test_the_audience_for_a_buried_section_is_the_people_not_reaching_it(): void
    {
        $scorer = new PriorityScorer;

        $this->assertSame(
            0.80,
            $scorer->audienceFor('drop_off_before_section', reach: 0.20),
            'Only 20% arrive, so the prize is the 80% who do not.'
        );
    }

    public function test_a_worse_buried_section_outranks_a_less_buried_one(): void
    {
        $scorer = new PriorityScorer;

        $badlyBuried = $scorer->score(
            trafficShare: $scorer->audienceFor('drop_off_before_section', reach: 0.10),
            severity: 4, confidence: 0.85, effort: 3,
        );

        $mildlyBuried = $scorer->score(
            trafficShare: $scorer->audienceFor('drop_off_before_section', reach: 0.45),
            severity: 4, confidence: 0.85, effort: 3,
        );

        $this->assertGreaterThan(
            $mildlyBuried,
            $badlyBuried,
            'The more badly buried a section is, the more urgent it should be — not the less.'
        );
    }

    public function test_every_other_rule_still_uses_the_people_who_do_reach_the_section(): void
    {
        $scorer = new PriorityScorer;

        $this->assertSame(
            0.82,
            $scorer->audienceFor('seen_but_not_clicked', reach: 0.82),
            'A button problem affects the people who actually see the button.'
        );
    }

    public function test_an_unknown_reach_still_falls_back_to_everyone(): void
    {
        $this->assertNull((new PriorityScorer)->audienceFor('drop_off_before_section', reach: null));
    }
}
