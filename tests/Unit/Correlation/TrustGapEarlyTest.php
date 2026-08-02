<?php

namespace Tests\Unit\Correlation;

use App\Services\Correlation\Rules\TrustGapEarly;
use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\Metrics;
use App\Services\Correlation\Support\Section;
use PHPUnit\Framework\TestCase;

class TrustGapEarlyTest extends TestCase
{
    private function snapshot(float $bounceRate, bool $trustProblemAboveFold, bool $hasProofBelow = true): AuditSnapshot
    {
        $heroProblems = $trustProblemAboveFold
            ? [[
                'what' => 'There is no testimonial, customer logo or security badge anywhere in the first screenful.',
                'why' => 'Visitors are asked to believe a claim with nothing to back it.',
                'fix' => 'Move two customer logos and one short quote above the fold.',
                'severity' => 4,
                'category' => 'trust',
            ]]
            : [[
                'what' => 'Three customer logos and a short quote sit right under the headline.',
                'why' => 'Proof arrives before the ask.',
                'fix' => 'Nothing to change here.',
                'severity' => 1,
                'category' => 'trust',
            ]];

        $sections = [new Section('Hero', 0, 880, 4200, 60, $heroProblems)];

        if ($hasProofBelow) {
            $sections[] = new Section('Testimonials', 2600, 900, 4200, 78);
        }

        return new AuditSnapshot(
            metrics: new Metrics(visitors: 8_000, bounceRate: $bounceRate, conversionRate: 1.1),
            sections: $sections,
        );
    }

    public function test_it_fires_when_there_is_no_proof_up_top_and_people_are_leaving(): void
    {
        $insight = (new TrustGapEarly)->evaluate($this->snapshot(bounceRate: 71.0, trustProblemAboveFold: true));

        $this->assertNotNull($insight, 'High bounce plus no proof above the fold — must fire.');
        $this->assertSame('trust_gap_early', $insight->ruleKey);
        $this->assertSame('Hero', $insight->sectionName);
        $this->assertSame('bounce_rate', $insight->evidence['metric']);
        $this->assertSame(71.0, $insight->evidence['value']);
    }

    public function test_it_stays_quiet_when_proof_is_already_above_the_fold(): void
    {
        $this->assertNull(
            (new TrustGapEarly)->evaluate($this->snapshot(bounceRate: 71.0, trustProblemAboveFold: false)),
            'The AI found proof up top, so there is no trust gap to report.'
        );
    }

    public function test_it_stays_quiet_when_people_are_not_actually_leaving(): void
    {
        $this->assertNull(
            (new TrustGapEarly)->evaluate($this->snapshot(bounceRate: 31.0, trustProblemAboveFold: true)),
            'A 31% bounce rate means the page is holding people — no evidence of a trust problem.'
        );
    }
}
