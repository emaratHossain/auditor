<?php

namespace App\Services;

/**
 * Works out which fix is worth doing first.
 *
 *     priority = (traffic share x severity x confidence) / effort
 *
 * A score rather than a feeling, so two people reading the same audit agree.
 * A tiny problem on a section 90% of people see beats a big problem on a
 * section 5% reach — that is the whole point of the traffic-share term.
 */
class PriorityScorer
{
    public const HIGH_ABOVE   = 1.60;
    public const MEDIUM_ABOVE = 0.60;

    /**
     * @param  float|null  $trafficShare  0–1, or null when the user did not supply
     *                                    per-section reach. Null means "assume
     *                                    everyone sees it" — assuming nobody does
     *                                    would silently bury real problems.
     * @param  int    $severity   1–5, from the AI
     * @param  float  $confidence 0–1, from the rule that fired
     * @param  int    $effort     1–5, a rough build estimate
     */
    public function score(?float $trafficShare, int $severity, float $confidence, int $effort): float
    {
        $share = $trafficShare ?? 1.0;
        $effort = max(1, $effort);

        return round(($share * $severity * $confidence) / $effort, 4);
    }

    /**
     * Which audience the traffic-share term should represent, for a given rule.
     *
     * For nearly every rule it is the people who reach the section, because that
     * is who meets the problem. `drop_off_before_section` is the exception: its
     * finding IS the low reach, so multiplying by that same number would rank a
     * badly buried section below a mildly buried one. The audience that matters
     * there is the share being lost.
     */
    public function audienceFor(string $ruleKey, ?float $reach): ?float
    {
        if ($reach === null) {
            return null;
        }

        return $ruleKey === 'drop_off_before_section'
            ? round(1.0 - $reach, 4)
            : $reach;
    }

    /** Three buckets only. More would be noise. */
    public function bucket(float $score): string
    {
        return match (true) {
            $score >= self::HIGH_ABOVE   => 'high',
            $score >= self::MEDIUM_ABOVE => 'medium',
            default                      => 'low',
        };
    }
}
