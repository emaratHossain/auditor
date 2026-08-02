<?php

namespace Tests\Unit\Correlation;

use App\Services\Correlation\Rules\DropOffBeforeSection;
use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\Metrics;
use App\Services\Correlation\Support\Section;
use PHPUnit\Framework\TestCase;

class DropOffBeforeSectionTest extends TestCase
{
    /** Pricing sits three quarters of the way down a 4,500px page. */
    private function snapshot(array $reach): AuditSnapshot
    {
        return new AuditSnapshot(
            metrics: new Metrics(
                visitors: 10_000,
                bounceRate: 58.0,
                conversionRate: 1.4,
                sectionReach: $reach,
            ),
            sections: [
                new Section('Hero', 0, 900, 4500, 71),
                new Section('Features', 900, 1200, 4500, 66),
                new Section('Testimonials', 2100, 1300, 4500, 62),
                new Section('Pricing', 3400, 1100, 4500, 74, [[
                    'what' => 'The plan comparison is clear once you get to it.',
                    'why' => 'Nothing here is stopping people who arrive.',
                    'fix' => 'Move it above the testimonials.',
                    'severity' => 3,
                    'category' => 'layout',
                ]]),
            ],
        );
    }

    public function test_it_fires_when_a_section_sits_below_where_most_visitors_stop_scrolling(): void
    {
        $insight = (new DropOffBeforeSection)->evaluate(
            $this->snapshot(['Hero' => 96.0, 'Features' => 71.0, 'Testimonials' => 44.0, 'Pricing' => 20.0])
        );

        $this->assertNotNull($insight, 'Only 20% reach Pricing — the rule must fire.');
        $this->assertSame('drop_off_before_section', $insight->ruleKey);
        $this->assertSame('Pricing', $insight->sectionName);
        $this->assertSame('section_reach', $insight->evidence['metric']);
        $this->assertSame(20.0, $insight->evidence['value']);
    }

    public function test_it_stays_quiet_when_people_are_reaching_the_section(): void
    {
        $this->assertNull(
            (new DropOffBeforeSection)->evaluate(
                $this->snapshot(['Hero' => 98.0, 'Features' => 92.0, 'Testimonials' => 88.0, 'Pricing' => 81.0])
            ),
            '81% reach is healthy — nothing is buried.'
        );
    }

    public function test_it_reports_the_worst_buried_section_not_merely_the_last_one(): void
    {
        $insight = (new DropOffBeforeSection)->evaluate(
            $this->snapshot(['Hero' => 96.0, 'Features' => 90.0, 'Testimonials' => 12.0, 'Pricing' => 31.0])
        );

        $this->assertNotNull($insight);
        $this->assertSame('Testimonials', $insight->sectionName, 'The 12% section is the worse problem.');
    }

    public function test_a_blank_section_reach_makes_the_rule_silent(): void
    {
        $this->assertNull(
            (new DropOffBeforeSection)->evaluate($this->snapshot([])),
            'Without scroll data the rule has nothing to stand on and must say nothing.'
        );
    }

    public function test_it_never_blames_a_section_at_the_top_of_the_page(): void
    {
        $this->assertNull(
            (new DropOffBeforeSection)->evaluate(['Hero' => 8.0] === [] ? $this->snapshot([]) : $this->snapshot(['Hero' => 8.0])),
            'A section at the very top cannot be buried; a low number there means something else is wrong.'
        );
    }
}
