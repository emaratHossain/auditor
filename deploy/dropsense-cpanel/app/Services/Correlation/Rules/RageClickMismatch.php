<?php

namespace App\Services\Correlation\Rules;

use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\InsightCandidate;

/**
 * Visitors click the same spot again and again because nothing happens.
 *
 * Almost always the same cause: something looks clickable and is not — a card
 * with a hover effect and no link, an image that reads as a button. Meanwhile
 * the real button sits there being ignored.
 *
 * This rule was written for V1 and cut, because V1 collected no click data. The
 * demo fixture now supplies it; the live Clarity API behind it is still V2.
 */
class RageClickMismatch implements Rule
{
    /** Below this, a few frustrated clicks are noise rather than a pattern. */
    private const MEANINGFUL_RAGE_CLICKS = 50;

    /** Above this click rate, the button is working and this is a different story. */
    private const HEALTHY_CLICK_RATE = 10.0;

    public function key(): string
    {
        return 'rage_click_mismatch';
    }

    public function evaluate(AuditSnapshot $snapshot): ?InsightCandidate
    {
        $clickRate = $snapshot->metrics->ctaClickRate;

        // The user left this blank. Say nothing rather than assume a zero.
        if ($clickRate === null) {
            return null;
        }

        // Both halves are required. Rage clicks on a page whose button works is
        // a different problem with a different fix.
        if ($clickRate > self::HEALTHY_CLICK_RATE) {
            return null;
        }

        foreach ($snapshot->sections as $section) {
            $rage = $snapshot->metrics->rageClicksFor($section->name);

            if ($rage === null || $rage < self::MEANINGFUL_RAGE_CLICKS) {
                continue;
            }

            // The numbers say people are clicking. The picture has to say what
            // they are clicking, or this is an insight that cannot prove itself.
            $problem = $section->worstProblemIn('ui', 'layout', 'cta');
            if ($problem === null) {
                continue;
            }

            $dead = $snapshot->metrics->deadClicksFor($section->name);
            $deadNote = $dead !== null
                ? sprintf(' Another %s clicks landed on nothing at all.', number_format($dead))
                : '';

            return new InsightCandidate(
                ruleKey: $this->key(),
                sectionName: $section->name,
                statement: sprintf(
                    'Visitors clicked %s times in frustration on %s while only %s%% pressed the real button.%s %s People are clicking something here that is not a button — the thing that looks clickable is not, and the thing that is does not look it.',
                    number_format($rage),
                    $section->name,
                    $clickRate,
                    $deadNote,
                    $problem['what'],
                ),
                evidence: [
                    'metric'     => 'rage_clicks',
                    'value'      => (float) $rage,
                    'unit'       => 'clicks',
                    'comparison' => sprintf('only %s%% of visitors press the real button', $clickRate),
                ],
                confidence: 0.85,
                severity: (int) ($problem['severity'] ?? 3),
                sourceProblem: $problem,
            );
        }

        return null;
    }
}
