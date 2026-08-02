<?php

namespace Tests\Unit\Correlation;

use App\Services\Correlation\Rules\MobileGap;
use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\Metrics;
use App\Services\Correlation\Support\Section;
use PHPUnit\Framework\TestCase;

class MobileGapTest extends TestCase
{
    private function snapshot(?float $mobileBounce, bool $withMobileProblem = true): AuditSnapshot
    {
        $problems = $withMobileProblem
            ? [[
                'what' => 'On a phone the headline is 11px and the button falls below the first screenful.',
                'why' => 'Visitors have to pinch to read and scroll to act.',
                'fix' => 'Raise body text to at least 16px and move the button above the fold on small screens.',
                'severity' => 5,
                'category' => 'mobile',
            ]]
            : [[
                'what' => 'The desktop spacing is a little tight.',
                'why' => 'Minor visual crowding.',
                'fix' => 'Add breathing room between blocks.',
                'severity' => 2,
                'category' => 'ui',
            ]];

        return new AuditSnapshot(
            metrics: new Metrics(
                visitors: 10_000,
                bounceRate: 44.0,
                conversionRate: 2.0,
                mobileShare: 68.0,
                mobileBounceRate: $mobileBounce,
            ),
            sections: [new Section('Hero', 0, 900, 4200, 48, $problems)],
        );
    }

    public function test_it_fires_when_the_phone_bounce_rate_is_far_worse_and_the_ai_saw_why(): void
    {
        $insight = (new MobileGap)->evaluate($this->snapshot(mobileBounce: 79.0));

        $this->assertNotNull($insight, 'Phone bounce 79% against 44% overall, with a mobile finding — must fire.');
        $this->assertSame('mobile_gap', $insight->ruleKey);
        $this->assertSame('mobile_bounce_rate', $insight->evidence['metric']);
        $this->assertSame(79.0, $insight->evidence['value']);
    }

    public function test_it_needs_both_halves_and_stays_quiet_without_a_mobile_finding(): void
    {
        $this->assertNull(
            (new MobileGap)->evaluate($this->snapshot(mobileBounce: 79.0, withMobileProblem: false)),
            'A bad number with no visual cause is not evidence — it is a coincidence.'
        );
    }

    public function test_it_stays_quiet_when_the_phone_is_doing_about_as_well_as_the_desktop(): void
    {
        $this->assertNull(
            (new MobileGap)->evaluate($this->snapshot(mobileBounce: 46.0)),
            '46% against 44% is noise, not a leak.'
        );
    }

    public function test_a_blank_phone_bounce_rate_makes_the_rule_silent(): void
    {
        $this->assertNull(
            (new MobileGap)->evaluate($this->snapshot(mobileBounce: null)),
            'A missing number must switch the rule off, never be read as a zero.'
        );
    }
}
