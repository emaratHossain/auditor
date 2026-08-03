<?php

namespace App\Services\Rewrite;

use App\Models\Audit;
use App\Models\Rewrite;
use App\Models\ScreenshotSection;
use InvalidArgumentException;

/**
 * Find-or-create one rewrite per section and element.
 *
 * The service is what makes this more than a thesaurus: it hands the model the
 * original words, the critique of that section, AND the correlation insight —
 * so the rewrite is told why the copy is failing, in numbers.
 */
class RewriteService
{
    public function __construct(
        private CopyRewriter $rewriter,
        private RewriteSchema $schema = new RewriteSchema,
        private RewritePrompt $prompt = new RewritePrompt,
    ) {}

    public function forElement(Audit $audit, string $sectionName, string $element): Rewrite
    {
        if (! in_array($element, Rewrite::ELEMENTS, true)) {
            throw new InvalidArgumentException(
                "There is nothing called '{$element}' to rewrite. Expected one of: ".implode(', ', Rewrite::ELEMENTS).'.'
            );
        }

        $section = $this->section($audit, $sectionName);
        $original = $this->originalText($section, $element);

        // The second click is free. A rehearsal must not cost as much as the demo.
        $stored = $audit->rewrites()
            ->where('section_name', $section->section_name)
            ->where('element', $element)
            ->first();

        if ($stored) {
            return $stored;
        }

        $result = $this->rewriter->rewrite(
            audit: $audit,
            sectionName: $section->section_name,
            element: $element,
            original: $original,
            critique: $this->prompt->critiqueFor($audit, $section->section_name),
            insight: $this->prompt->insightFor($audit, $section->section_name),
        );

        // Validate before anything is written. A half-saved rewrite that looks
        // finished is worse than an error saying the call failed.
        $validated = $this->schema->validate(['variants' => $result->variants]);

        return $audit->rewrites()->create([
            'section_name' => $section->section_name,
            'element'      => $element,
            'original'     => $original,
            'variants'     => $validated['variants'],
            'model'        => $result->model,
            'tokens'       => $result->tokens,
        ]);
    }

    private function section(Audit $audit, string $sectionName): ScreenshotSection
    {
        // Case-insensitively, because the name came off a real page and the
        // caller is echoing back what the report showed them.
        $section = $audit->sections()
            ->where('viewport', 'desktop')
            ->get()
            ->first(fn (ScreenshotSection $s) => strcasecmp($s->section_name, $sectionName) === 0);

        if (! $section) {
            throw new InvalidArgumentException("This audit has no section called '{$sectionName}'.");
        }

        return $section;
    }

    private function originalText(ScreenshotSection $section, string $element): string
    {
        $copy = $section->copy ?? [];

        $text = $element === 'cta'
            ? ($copy['ctas'][0]['text'] ?? null)
            : ($copy[$element]['text'] ?? null);

        if (! is_string($text) || trim($text) === '') {
            throw new InvalidArgumentException(
                "We could not read a {$element} on {$section->section_name}, so there is no {$element} to rewrite."
            );
        }

        return $text;
    }
}
