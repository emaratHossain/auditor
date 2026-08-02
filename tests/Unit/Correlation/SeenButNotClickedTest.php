<?php

namespace Tests\Unit\Correlation;

use App\Services\Correlation\Rules\SeenButNotClicked;
use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\Metrics;
use App\Services\Correlation\Support\Section;
use PHPUnit\Framework\TestCase;

class SeenButNotClickedTest extends TestCase
{
    private function snapshot(?float $ctaClickRate, float $heroReach = 82.0): AuditSnapshot
    {
        return new AuditSnapshot(
            metrics: new Metrics(
                visitors: 10_000,
                bounceRate: 62.0,
                conversionRate: 2.1,
                ctaClickRate: $ctaClickRate,
                sectionReach: ['Hero' => $heroReach],
            ),
            sections: [
                new Section(
                    name: 'Hero',
                    position: 0,
                    height: 900,
                    pageHeight: 4500,
                    aiScore: 55,
                    problems: [[
                        'what'     => 'The main button barely stands out from its background.',
                        'why'      => 'Visitors do not register it as the thing to press.',
                        'fix'      => 'Raise the contrast to at least 4.5:1 and enlarge the tap target.',
                        'severity' => 4,
                        'category' => 'cta',
                    ]],
                ),
            ],
        );
    }

    public function test_it_fires_when_most_visitors_see_the_button_but_almost_nobody_clicks(): void
    {
        $insight = (new SeenButNotClicked)->evaluate($this->snapshot(ctaClickRate: 2.0));

        $this->assertNotNull($insight, 'Expected the rule to fire on 82% reach with a 2% click rate.');
        $this->assertSame('seen_but_not_clicked', $insight->ruleKey);
        $this->assertSame('Hero', $insight->sectionName);
        $this->assertSame('cta_click_rate', $insight->evidence['metric']);
        $this->assertSame(2.0, $insight->evidence['value']);
    }

    public function test_it_stays_quiet_when_the_button_is_doing_its_job(): void
    {
        $this->assertNull(
            (new SeenButNotClicked)->evaluate($this->snapshot(ctaClickRate: 25.0)),
            'A 25% click rate is not a problem — the rule must not fire.'
        );
    }

    public function test_a_blank_click_rate_makes_the_rule_silent_rather_than_assuming_a_value(): void
    {
        $this->assertNull(
            (new SeenButNotClicked)->evaluate($this->snapshot(ctaClickRate: null)),
            'A missing number must switch the rule off, never be read as a zero.'
        );
    }

    public function test_it_stays_quiet_when_hardly_anyone_reaches_the_button(): void
    {
        $this->assertNull(
            (new SeenButNotClicked)->evaluate($this->snapshot(ctaClickRate: 2.0, heroReach: 9.0)),
            'If almost nobody sees the button, a low click rate is a traffic problem, not a button problem.'
        );
    }
}
