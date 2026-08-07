<?php

namespace App\Services\Correlation\Rules;

use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\InsightCandidate;

/**
 * People on phones are leaving far faster than people on laptops, and the AI can
 * see why.
 *
 * Deliberately needs BOTH halves. A bad number on its own is a coincidence; a
 * visual cause on its own is an opinion. Together they are evidence.
 */
class MobileGap implements Rule
{
    /** How many percentage points worse the phone has to be before it is a real gap. */
    private const MEANINGFUL_GAP = 15.0;

    public function key(): string
    {
        return 'mobile_gap';
    }

    public function evaluate(AuditSnapshot $snapshot): ?InsightCandidate
    {
        $mobileBounce = $snapshot->metrics->mobileBounceRate;

        // The user left this blank. Say nothing rather than assume a zero.
        if ($mobileBounce === null) {
            return null;
        }

        $gap = $mobileBounce - $snapshot->metrics->bounceRate;
        if ($gap < self::MEANINGFUL_GAP) {
            return null;
        }

        // Find the section where the AI actually saw a phone-specific problem.
        foreach ($snapshot->sections as $section) {
            $problem = $section->worstProblemIn('mobile');
            if ($problem === null) {
                continue;
            }

            $share = $snapshot->metrics->mobileShare;
            $shareNote = $share !== null
                ? sprintf(' %s%% of your visitors are on a phone, so this is where the money is going.', $share)
                : '';

            return new InsightCandidate(
                ruleKey: $this->key(),
                sectionName: $section->name,
                statement: sprintf(
                    'On a phone %s%% of visitors leave without doing anything, against %s%% overall. %s%s Fix the phone layout before anything else on this page.',
                    $mobileBounce,
                    $snapshot->metrics->bounceRate,
                    $problem['what'],
                    $shareNote,
                ),
                evidence: [
                    'metric'     => 'mobile_bounce_rate',
                    'value'      => $mobileBounce,
                    'unit'       => '%',
                    'comparison' => sprintf('%s percentage points worse than the %s%% overall bounce rate', round($gap, 1), $snapshot->metrics->bounceRate),
                ],
                confidence: 0.85,
                severity: (int) ($problem['severity'] ?? 4),
                sourceProblem: $problem,
            );
        }

        // A bad number with no visual cause is not evidence.
        return null;
    }
}
