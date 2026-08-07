<?php

namespace App\Services\Correlation\Rules;

use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\InsightCandidate;

/**
 * Most visitors reach the main button and almost none of them press it.
 *
 * The commonest landing page failure, and the one people most often misread as a
 * traffic problem. It is not: they found the button and ignored it.
 */
class SeenButNotClicked implements Rule
{
    /** Below this click rate, the button is not doing its job. */
    private const POOR_CLICK_RATE = 5.0;

    /** Below this reach, a low click rate says more about traffic than about the button. */
    private const MEANINGFUL_REACH = 0.40;

    public function key(): string
    {
        return 'seen_but_not_clicked';
    }

    public function evaluate(AuditSnapshot $snapshot): ?InsightCandidate
    {
        $clickRate = $snapshot->metrics->ctaClickRate;

        // The user left this blank. Say nothing rather than assume a zero.
        if ($clickRate === null) {
            return null;
        }

        if ($clickRate >= self::POOR_CLICK_RATE) {
            return null;
        }

        $section = $snapshot->sectionWithCta();
        if ($section === null) {
            return null;
        }

        $reach = $snapshot->metrics->reachFor($section->name);
        if ($reach === null || $reach < self::MEANINGFUL_REACH) {
            return null;
        }

        $problem = $section->worstProblemIn('cta');
        if ($problem === null) {
            return null;
        }

        $reachPercent = round($reach * 100, 1);

        return new InsightCandidate(
            ruleKey: $this->key(),
            sectionName: $section->name,
            statement: sprintf(
                '%s%% of visitors reach %s, but only %s%% press the button. %s People are finding the button and ignoring it, so the fix is the button itself — not more traffic.',
                $reachPercent,
                $section->name,
                $clickRate,
                $problem['what'],
            ),
            evidence: [
                'metric'     => 'cta_click_rate',
                'value'      => $clickRate,
                'unit'       => '%',
                'comparison' => sprintf('%s%% of visitors see this section', $reachPercent),
            ],
            confidence: 0.9,
            severity: (int) ($problem['severity'] ?? 3),
            sourceProblem: $problem,
        );
    }
}
