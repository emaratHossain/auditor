<?php

namespace App\Services\Correlation;

use App\Models\Audit;
use App\Services\Correlation\Rules\DropOffBeforeSection;
use App\Services\Correlation\Rules\MobileGap;
use App\Services\Correlation\Rules\Rule;
use App\Services\Correlation\Rules\SeenButNotClicked;
use App\Services\Correlation\Rules\TrustGapEarly;
use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\Metrics;
use App\Services\Correlation\Support\Section;

/**
 * The heart of the product. Everything before it is data collection; everything
 * after it is presentation.
 */
class CorrelationService
{
    public function __construct(private EvidenceGuarantee $guarantee = new EvidenceGuarantee) {}

    /** @return array<int,Rule> */
    public function rules(): array
    {
        return [
            new SeenButNotClicked,
            new DropOffBeforeSection,
            new MobileGap,
            new TrustGapEarly,
        ];
    }

    /** Runs every rule, throws away anything unprovable, saves what survives. */
    public function correlate(Audit $audit): int
    {
        $snapshot = $this->snapshot($audit);

        $candidates = [];
        foreach ($this->rules() as $rule) {
            if ($candidate = $rule->evaluate($snapshot)) {
                $candidates[] = $candidate;
            }
        }

        // Nothing reaches the user without a metric, a number and a section.
        $proven = $this->guarantee->filter($candidates);

        foreach ($proven as $candidate) {
            $audit->insights()->create([
                'section_name' => $candidate->sectionName,
                'rule_key'     => $candidate->ruleKey,
                'statement'    => $candidate->statement,
                'evidence'     => $candidate->evidence + ['source_problem' => $candidate->sourceProblem],
                'confidence'   => $candidate->confidence,
                'severity'     => $candidate->severity,
            ]);
        }

        return count($proven);
    }

    /** Turns Eloquent rows into the plain objects the rules understand. */
    public function snapshot(Audit $audit): AuditSnapshot
    {
        $m = $audit->metrics;

        $metrics = new Metrics(
            visitors: $m?->visitors ?? 0,
            bounceRate: $m?->bounce_rate ?? 0.0,
            conversionRate: $m?->conversion_rate ?? 0.0,
            ctaClickRate: $m?->cta_click_rate,
            mobileShare: $m?->mobile_share,
            mobileBounceRate: $m?->mobile_bounce_rate,
            sectionReach: $m?->section_reach ?? [],
        );

        $findings = $audit->findings->keyBy(fn ($f) => strtolower($f->section_name));

        $sections = [];
        foreach ($audit->sections()->where('viewport', 'desktop')->orderBy('sort_order')->get() as $row) {
            $finding = $findings->get(strtolower($row->section_name));

            $sections[] = new Section(
                name: $row->section_name,
                position: $row->position,
                height: $row->height,
                pageHeight: $row->page_height,
                aiScore: $finding?->ai_score ?? 0,
                problems: $finding?->problems ?? [],
            );
        }

        return new AuditSnapshot($metrics, $sections);
    }
}
