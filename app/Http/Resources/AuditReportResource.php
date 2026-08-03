<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * The whole report in ONE response, about 40 KB.
 *
 * Splitting this into ten tidy endpoints feels cleaner and makes the screen
 * visibly slower, so it stays as one.
 */
class AuditReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metrics = $this->metrics;
        $findings = $this->findings->keyBy(fn ($f) => strtolower($f->section_name));

        return [
            'id'     => $this->id,
            'status' => $this->status,
            'stage'  => $this->stage,
            'stage_label'   => $this->stageLabel(),
            'error_message' => $this->error_message,
            'ran_at'        => $this->created_at?->toIso8601String(),

            'page' => [
                'id'   => $this->page->id,
                'name' => $this->page->name,
                'url'  => $this->page->url,
            ],

            'score' => [
                'overall'    => $this->overall_score,
                'categories' => $this->categoryBreakdown(),
            ],

            // The two or three things Lighthouse scored worst, so "71" is
            // something a reader can act on rather than a number to admire.
            'lighthouse' => $this->lighthouse
                ? ['worst_checks' => $this->lighthouse['worst_checks'] ?? []]
                : null,

            // The report says where its numbers came from. Demo data is labelled,
            // not hidden — that is what keeps the evidence honest.
            'metrics_source' => [
                'key'   => $metrics?->source ?? 'manual',
                'label' => ($metrics?->source ?? 'manual') === 'demo'
                    ? config('demo-analytics.label')
                    : 'Numbers you entered yourself',
            ],

            // Every number carries a plain sentence saying what it means, because a
            // screen that only shows numbers is the problem this product solves.
            'metrics' => $metrics ? [
                ['key' => 'visitors',           'label' => 'Visitors',                 'value' => $metrics->visitors,           'unit' => '',  'explain' => 'How many people came to this page in the period you entered.'],
                ['key' => 'bounce_rate',        'label' => 'Bounce rate',              'value' => $metrics->bounce_rate,        'unit' => '%', 'explain' => 'The share who arrive and leave without doing anything.'],
                ['key' => 'conversion_rate',    'label' => 'Conversion rate',          'value' => $metrics->conversion_rate,    'unit' => '%', 'explain' => 'The share who did the thing you wanted — signed up, bought, booked.'],
                ['key' => 'cta_click_rate',     'label' => 'Main button click rate',   'value' => $metrics->cta_click_rate,     'unit' => '%', 'explain' => 'The share who press the main button. Needs an event on the button to measure.'],
                ['key' => 'mobile_share',       'label' => 'Visitors on a phone',      'value' => $metrics->mobile_share,       'unit' => '%', 'explain' => 'How much of your traffic is on a small screen.'],
                ['key' => 'mobile_bounce_rate', 'label' => 'Bounce rate on a phone',   'value' => $metrics->mobile_bounce_rate, 'unit' => '%', 'explain' => 'The same leaving-without-acting figure, but only for phones.'],
            ] : [],

            'sections' => $this->sections->where('viewport', 'desktop')->values()->map(function ($section) use ($findings) {
                $finding = $findings->get(strtolower($section->section_name));

                return [
                    'name'           => $section->section_name,
                    'screenshot_url' => $this->signedScreenshot($section->screenshot_path),
                    'mobile_url'     => $this->signedScreenshot(
                        $this->sections->firstWhere(fn ($s) => $s->viewport === 'mobile' && $s->section_name === $section->section_name)?->screenshot_path
                    ),
                    'position_percent' => (int) round($section->depth() * 100),
                    'above_the_fold'   => $section->isAboveTheFold(),
                    // The section's own words, so the report can show them as
                    // text and offer to rewrite them — not only as a picture.
                    'copy'             => $section->copy,
                    'ai_score'         => $finding?->ai_score,
                    'problems'         => $finding?->problems ?? [],
                ];
            }),

            'recommendations' => $this->recommendations->map(fn ($rec) => [
                'id'              => $rec->id,
                'section'         => $rec->section_name,
                'title'           => $rec->title,
                'evidence'        => $rec->evidence,
                'suggested_fix'   => $rec->suggested_fix,
                'expected_impact' => $rec->expected_impact,
                'priority'        => $rec->priority,
                'priority_score'  => $rec->priority_score,
                'effort'          => $rec->effort,
            ]),

            'insights' => $this->insights->map(fn ($i) => [
                'section'   => $i->section_name,
                'rule'      => $i->rule_key,
                'statement' => $i->statement,
                'evidence'  => collect($i->evidence)->except('source_problem'),
            ]),

            // Rides along in the one response rather than needing a call of its
            // own — and it is what the panel falls back to when a live call fails.
            'rewrites' => $this->rewrites->map(fn ($r) => [
                'id'       => $r->id,
                'section'  => $r->section_name,
                'element'  => $r->element,
                'original' => $r->original,
                'variants' => $r->variants,
                'model'    => $r->model,
            ]),

            'cost' => [
                'usd'   => (float) $this->token_cost,
                'model' => $this->ai_model,
            ],
        ];
    }

    private function categoryBreakdown(): array
    {
        $stored = $this->category_scores ?? [];

        return collect($stored)
            ->filter(fn ($v) => is_array($v) && array_key_exists('label', $v))
            ->values()
            ->all();
    }

    /** Never a raw disk path — a leaked link should not expose a client's page forever. */
    private function signedScreenshot(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return URL::temporarySignedRoute('screenshot', now()->addHour(), ['path' => $path]);
    }
}
