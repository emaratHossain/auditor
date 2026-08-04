<?php

namespace App\Jobs;

use App\Models\Audit;
use App\Services\HealthScorer;
use App\Services\RecommendationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RankAndScoreJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(public int $auditId) {}

    public function handle(RecommendationEngine $engine, HealthScorer $scorer): void
    {
        // The page this belongs to can be deleted while its chain is still
        // queued. That is a decision, not a failure — say nothing and stop.
        if (! $audit = Audit::find($this->auditId)) {
            return;
        }
        $audit->markStage('scoring');

        $engine->generate($audit);

        [$scores, $measured] = $this->categoryScores($audit);

        $result = $scorer->score($scores, $measured);

        $audit->update([
            'status'          => Audit::STATUS_COMPLETED,
            'stage'           => null,
            'overall_score'   => $result['overall'],
            'category_scores' => $result['categories'],
            'completed_at'    => now(),
        ]);
    }

    /**
     * Each category is scored from what we actually measured. Anything we could
     * not judge is left null so HealthScorer drops it from the average rather
     * than scoring it zero.
     *
     * @return array{0:array<string,int|null>, 1:array<int,string>} the scores, and
     *                                                             the keys that were
     *                                                             measured rather
     *                                                             than estimated.
     */
    private function categoryScores(Audit $audit): array
    {
        $findings = $audit->findings;
        $metrics = $audit->metrics;

        if ($findings->isEmpty()) {
            return [[], []];
        }

        $penalty = function (string ...$categories) use ($findings): ?int {
            $worst = 0;
            $seen = false;

            foreach ($findings as $finding) {
                foreach ($finding->problems ?? [] as $problem) {
                    if (in_array($problem['category'] ?? '', $categories, true)) {
                        $seen = true;
                        $worst = max($worst, (int) ($problem['severity'] ?? 0));
                    }
                }
            }

            // Nothing flagged in this category across the whole page: that is a
            // good sign, not an absence of data.
            return $seen ? max(0, 100 - $worst * 15) : ($findings->isNotEmpty() ? 88 : null);
        };

        $visualAverage = (int) round($findings->avg('ai_score'));

        $cta = $penalty('cta');
        if ($metrics?->cta_click_rate !== null) {
            // Weight the real click rate in alongside the AI's judgement.
            $observed = min(100, (int) round($metrics->cta_click_rate * 8));
            $cta = (int) round(($cta + $observed) / 2);
        }

        $ux = $metrics
            ? max(0, min(100, (int) round(100 - $metrics->bounce_rate)))
            : null;

        // Lighthouse measured these two. When it failed the column is null, so
        // they fall back to the AI estimate and stay labelled as estimates —
        // the score never silently changes meaning.
        $lighthouse = $audit->lighthouse ?? [];
        $measured = [];

        $performance = $penalty('performance');
        if (is_int($lighthouse['performance'] ?? null)) {
            $performance = $lighthouse['performance'];
            $measured[] = 'performance';
        }

        $accessibility = $penalty('accessibility', 'ui');
        if (is_int($lighthouse['accessibility'] ?? null)) {
            $accessibility = $lighthouse['accessibility'];
            $measured[] = 'accessibility';
        }

        return [[
            'cta'           => $cta,
            'ux'            => $ux,
            'ui'            => $visualAverage,
            'trust'         => $penalty('trust'),
            'performance'   => $performance,
            'accessibility' => $accessibility,
        ], $measured];
    }
}
