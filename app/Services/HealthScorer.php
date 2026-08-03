<?php

namespace App\Services;

/**
 * One number out of 100 for the whole page.
 *
 * The weights live here and are shown in the app, because a number nobody can
 * take apart is a number nobody should trust.
 *
 * Worth saying out loud: 79 means nothing on its own. 79 last month and 86 today
 * means the fixes worked. V1 can only ever show the first half of that sentence.
 */
class HealthScorer
{
    /** @var array<string,int> must sum to 100 */
    public const WEIGHTS = [
        'cta'           => 25,
        'ux'            => 20,
        'ui'            => 20,
        'trust'         => 15,
        'performance'   => 10,
        'accessibility' => 10,
    ];

    public const LABELS = [
        'cta'           => 'Main button effectiveness',
        'ux'            => 'User experience',
        'ui'            => 'Visual design',
        'trust'         => 'Trust',
        'performance'   => 'Speed',
        'accessibility' => 'Accessibility',
    ];

    /**
     * Only true while these numbers are estimates.
     *
     * A category Lighthouse actually measured carries no caveat, because there
     * is nothing left to warn about — and deleting a warning is only honest
     * once the thing it warned about has genuinely gone.
     *
     * @var array<string,string>
     */
    public const CAVEATS = [
        'accessibility' => 'An AI estimate based on contrast and font size — not a real accessibility check.',
        'performance'   => 'An estimate based on a single page load, not a full performance check.',
    ];

    /**
     * @param  array<string,int|float|null>  $categoryScores  0–100 per category; omit
     *                                                        or pass null for anything
     *                                                        there was no data to judge.
     * @param  array<int,string>  $measured  category keys whose number was measured
     *                                       rather than estimated.
     * @return array{overall:int|null, categories:array<string,array<string,mixed>>}
     */
    public function score(array $categoryScores, array $measured = []): array
    {
        $breakdown = [];
        $weightedTotal = 0.0;
        $weightUsed = 0;

        foreach (self::WEIGHTS as $key => $weight) {
            $raw = $categoryScores[$key] ?? null;
            $wasMeasured = in_array($key, $measured, true);

            $row = [
                'label'    => self::LABELS[$key],
                'weight'   => $weight,
                'score'    => null,
                'measured' => $wasMeasured,
                'caveat'   => $wasMeasured ? null : (self::CAVEATS[$key] ?? null),
            ];

            if ($raw === null) {
                // Not scored, so it is left out of the average entirely. Treating a
                // missing category as a zero would punish the user for a number we
                // failed to collect.
                $breakdown[$key] = $row;

                continue;
            }

            $clamped = (int) round(max(0, min(100, $raw)));

            $row['score'] = $clamped;
            $breakdown[$key] = $row;

            $weightedTotal += $clamped * $weight;
            $weightUsed += $weight;
        }

        return [
            'overall'    => $weightUsed > 0 ? (int) round($weightedTotal / $weightUsed) : null,
            'categories' => $breakdown,
        ];
    }
}
