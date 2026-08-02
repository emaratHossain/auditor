<?php

namespace App\Services\Correlation\Rules;

use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\InsightCandidate;

/**
 * Visitors are leaving before they have been given any reason to believe you.
 *
 * Replaces the original plan's rage-click rule, which needed click-tracking data
 * V1 does not collect. This one runs on the bounce rate, which is always present.
 */
class TrustGapEarly implements Rule
{
    /** Above this, enough people are leaving that it is worth explaining why. */
    private const HIGH_BOUNCE = 55.0;

    /** Below this severity the AI is describing proof that exists, not a gap. */
    private const REAL_GAP = 3;

    public function key(): string
    {
        return 'trust_gap_early';
    }

    public function evaluate(AuditSnapshot $snapshot): ?InsightCandidate
    {
        $bounceRate = $snapshot->metrics->bounceRate;

        if ($bounceRate < self::HIGH_BOUNCE) {
            return null;
        }

        foreach ($snapshot->sectionsAboveTheFold() as $section) {
            $problem = $section->worstProblemIn('trust');

            if ($problem === null || (int) ($problem['severity'] ?? 0) < self::REAL_GAP) {
                continue;
            }

            return new InsightCandidate(
                ruleKey: $this->key(),
                sectionName: $section->name,
                statement: sprintf(
                    '%s%% of visitors leave without doing anything, and %s',
                    $bounceRate,
                    lcfirst($problem['what']).' Add proof — a customer quote, two recognisable logos, or a security badge — before you ask for anything.',
                ),
                evidence: [
                    'metric'     => 'bounce_rate',
                    'value'      => $bounceRate,
                    'unit'       => '%',
                    'comparison' => sprintf('no proof of any kind in %s, the first thing visitors see', $section->name),
                ],
                confidence: 0.7,
                severity: (int) ($problem['severity'] ?? 3),
                sourceProblem: $problem,
            );
        }

        return null;
    }
}
