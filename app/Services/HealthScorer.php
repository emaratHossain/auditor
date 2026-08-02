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
     * Honest labelling beats a number that implies more rigour than it has.
     *
     * @var array<string,string>
     */
    public const CAVEATS = [
        'accessibility' => 'An AI estimate based on contrast and font size — not a real accessibility audit.',
        'performance'   => 'Based on a single page load, not a full performance audit.',
    ];

    /**
     * @param  array<string,int|float|null>  $categoryScores  0–100 per category; omit
     *                                                        or pass null for anything
     *                                                        there was no data to judge.
     * @return array{overall:int|null, categories:array<string,array<string,mixed>>}
     */
    public function score(array $categoryScores): array
    {
        $breakdown = [];
        $weightedTotal = 0.0;
        $weightUsed = 0;

        foreach (self::WEIGHTS as $key => $weight) {
            $raw = $categoryScores[$key] ?? null;

            if ($raw === null) {
                // Not scored, so it is left out of the average entirely. Treating a
                // missing category as a zero would punish the user for a number we
                // failed to collect.
                $breakdown[$key] = [
                    'label'  => self::LABELS[$key],
                    'weight' => $weight,
                    'score'  => null,
                    'caveat' => self::CAVEATS[$key] ?? null,
                ];

                continue;
            }

            $clamped = (int) round(max(0, min(100, $raw)));

            $breakdown[$key] = [
                'label'  => self::LABELS[$key],
                'weight' => $weight,
                'score'  => $clamped,
                'caveat' => self::CAVEATS[$key] ?? null,
            ];

            $weightedTotal += $clamped * $weight;
            $weightUsed += $weight;
        }

        return [
            'overall'    => $weightUsed > 0 ? (int) round($weightedTotal / $weightUsed) : null,
            'categories' => $breakdown,
        ];
    }
}
