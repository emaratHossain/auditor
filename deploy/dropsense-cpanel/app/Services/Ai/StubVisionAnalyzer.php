<?php

namespace App\Services\Ai;

use App\Models\Audit;

/**
 * A stand-in that costs nothing and needs no network.
 *
 * This is what makes the walking skeleton possible: the whole pipeline runs end
 * to end on day one, and each real piece gets swapped in behind the same
 * interface. It is also the safety net for demo day — if the network dies, set
 * AI_DRIVER=stub and the app still works.
 *
 * It is deliberately NOT random. The same page always produces the same
 * findings, so a demo rehearsal matches the demo.
 */
class StubVisionAnalyzer implements VisionAnalyzer
{
    public function analyse(Audit $audit): AnalysisResult
    {
        $sections = [];

        foreach ($audit->sections()->where('viewport', 'desktop')->get() as $section) {
            $sections[] = [
                'section'  => $section->section_name,
                'score'    => $this->scoreFor($section->section_name),
                'problems' => $this->problemsFor($section->section_name, $section->isAboveTheFold()),
            ];
        }

        return new AnalysisResult(
            sections: $sections,
            model: $this->modelName(),
            tokens: 0,
            cost: 0.0,
            raw: ['note' => 'Generated locally by the stub driver. No AI was called.'],
        );
    }

    public function modelName(): string
    {
        return 'stub';
    }

    private function scoreFor(string $name): int
    {
        // Deterministic but varied, so the report looks like a real report.
        return 45 + (crc32(strtolower($name)) % 40);
    }

    private function problemsFor(string $name, bool $aboveTheFold): array
    {
        $n = strtolower($name);
        $problems = [];

        if (str_contains($n, 'hero') || str_contains($n, 'header') || $aboveTheFold) {
            $problems[] = [
                'what'     => 'The main button sits on a background of almost the same tone, so it does not read as the thing to press.',
                'why'      => 'Visitors scan for the next action and find nothing that looks like one.',
                'fix'      => 'Raise the button contrast to at least 4.5:1 against its background and increase its height to 48px.',
                'severity' => 4,
                'category' => 'cta',
            ];
            $problems[] = [
                'what'     => 'There is no testimonial, customer logo or security badge anywhere in the first screenful.',
                'why'      => 'The page makes a claim and offers nothing to back it before asking for a commitment.',
                'fix'      => 'Move two recognisable customer logos and one short quote above the fold.',
                'severity' => 4,
                'category' => 'trust',
            ];
            $problems[] = [
                'what'     => 'On a phone the headline renders around 11px and the button falls below the first screenful.',
                'why'      => 'Visitors have to pinch to read and scroll before they can act.',
                'fix'      => 'Set body text to at least 16px on small screens and pull the button above the fold.',
                'severity' => 5,
                'category' => 'mobile',
            ];
        }

        // The demo fixture records 340 rage clicks on Features. The findings have
        // to describe the thing people are clicking, or RageClickMismatch stays
        // correctly silent and the fifth rule never appears on stage — the
        // numbers and the picture must tell the same story.
        if (str_contains($n, 'feature')) {
            $problems[] = [
                'what'     => 'The feature cards lift and change colour on hover, but clicking one does nothing.',
                'why'      => 'A hover effect is a promise that something is clickable. Visitors take it literally.',
                'fix'      => 'Either link each card to its detail section, or remove the hover effect so it stops promising.',
                'severity' => 4,
                'category' => 'ui',
            ];
        }

        if (str_contains($n, 'pricing') || str_contains($n, 'plan')) {
            $problems[] = [
                'what'     => 'The plans are readable, but this section sits a long way down the page.',
                'why'      => 'People who would have bought never see the price.',
                'fix'      => 'Move pricing above the testimonials, or repeat the main button just before it.',
                'severity' => 4,
                'category' => 'layout',
            ];
        }

        if ($problems === []) {
            $problems[] = [
                'what'     => 'The heading states a feature rather than an outcome the reader wants.',
                'why'      => 'Readers skim headings; a feature name gives them no reason to keep going.',
                'fix'      => 'Rewrite the heading as the result the visitor gets, in their own words.',
                'severity' => 2,
                'category' => 'content',
            ];
        }

        return $problems;
    }
}
