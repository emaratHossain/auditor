<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Insight;

/**
 * Turns each proven insight into a fix a designer can start on today.
 *
 * No AI call happens here. The problem and the fix already came back from the
 * vision pass; the rule supplied the evidence; the priority is arithmetic.
 */
class RecommendationEngine
{
    public function __construct(private PriorityScorer $scorer = new PriorityScorer) {}

    public function generate(Audit $audit): int
    {
        $metrics = $audit->metrics;
        $created = 0;

        foreach ($audit->insights as $insight) {
            $problem = $insight->evidence['source_problem'] ?? null;

            $reach = $metrics?->reachFor($insight->section_name);
            $trafficShare = $this->scorer->audienceFor($insight->rule_key, $reach);
            $effort = $this->effortFor($problem['category'] ?? null);

            $score = $this->scorer->score(
                trafficShare: $trafficShare,
                severity: $insight->severity,
                confidence: $insight->confidence,
                effort: $effort,
            );

            $audit->recommendations()->create([
                'insight_id'      => $insight->id,
                'section_name'    => $insight->section_name,
                'title'           => $this->title($insight, $problem),
                'evidence'        => $this->evidenceLine($insight),
                'suggested_fix'   => $problem['fix'] ?? 'Review this section against the finding above.',
                'expected_impact' => $this->expectedImpact($insight, $metrics),
                'priority'        => $this->scorer->bucket($score),
                'priority_score'  => $score,
                'effort'          => $effort,
                'severity'        => $insight->severity,
                'traffic_share'   => $trafficShare ?? 1.0,
                'confidence'      => $insight->confidence,
            ]);

            $created++;
        }

        return $created;
    }

    private function title(Insight $insight, ?array $problem): string
    {
        if ($problem && ! empty($problem['what'])) {
            return rtrim($problem['what'], '.');
        }

        return match ($insight->rule_key) {
            'seen_but_not_clicked'    => 'The main button is being seen and ignored',
            'drop_off_before_section' => "Hardly anyone reaches {$insight->section_name}",
            'mobile_gap'              => 'The phone layout is losing people',
            'trust_gap_early'         => 'Visitors leave before they have a reason to believe you',
            default                   => "Problem in {$insight->section_name}",
        };
    }

    /** Always carries a real number — that is the point of the whole product. */
    private function evidenceLine(Insight $insight): string
    {
        $e = $insight->evidence;
        $metric = str_replace('_', ' ', $e['metric'] ?? 'metric');
        $value = $e['value'] ?? null;
        $unit = $e['unit'] ?? '';

        $line = sprintf('%s in %s: %s%s.', ucfirst($metric), $insight->section_name, $value, $unit);

        if (! empty($e['comparison'])) {
            $line .= ' '.ucfirst($e['comparison']).'.';
        }

        return $line;
    }

    /**
     * Always a range. A single predicted number would be more precise than the
     * evidence supports, and precision we have not earned is dishonest.
     */
    private function expectedImpact(Insight $insight, $metrics): string
    {
        return match ($insight->rule_key) {
            'seen_but_not_clicked' => $metrics?->cta_click_rate !== null
                ? sprintf('Button clicks %s%% → %s-%s%%', $metrics->cta_click_rate, round($metrics->cta_click_rate * 2, 1), round($metrics->cta_click_rate * 3, 1))
                : 'A meaningful lift in button clicks',
            'drop_off_before_section' => sprintf('More visitors reaching %s, which is the precondition for it converting at all', $insight->section_name),
            'mobile_gap' => $metrics?->mobile_bounce_rate !== null
                ? sprintf('Phone bounce %s%% → closer to the %s%% you see overall', $metrics->mobile_bounce_rate, $metrics->bounce_rate)
                : 'Phone bounce closer to your desktop figure',
            'trust_gap_early' => $metrics?->bounce_rate !== null
                ? sprintf('Bounce %s%% → %s-%s%%', $metrics->bounce_rate, round($metrics->bounce_rate * 0.85), round($metrics->bounce_rate * 0.92))
                : 'A lower bounce rate',
            default => 'Improvement on the metric named above',
        };
    }

    private function effortFor(?string $category): int
    {
        return (int) (config('scoring.effort')[$category] ?? 3);
    }
}
