<?php

namespace App\Services\Rewrite;

use App\Models\Audit;

/**
 * The three things that stop this being a thesaurus.
 *
 * A rewrite tool handed only the old words can guess at style. This one is
 * handed the critique of the section and the number that proves the copy is
 * failing, and its stated reason has to refer to them.
 */
class RewritePrompt
{
    /** What the vision pass said about this section, as plain sentences. */
    public function critiqueFor(Audit $audit, string $sectionName): string
    {
        $finding = $audit->findings
            ->first(fn ($f) => strcasecmp($f->section_name, $sectionName) === 0);

        $problems = collect($finding?->problems ?? [])
            ->map(fn ($p) => '- '.trim(($p['what'] ?? '').' '.($p['why'] ?? '')))
            ->implode("\n");

        return trim($problems) ?: 'No specific problem was flagged on this section.';
    }

    /** The correlation insight for this section, if one survived the evidence guarantee. */
    public function insightFor(Audit $audit, string $sectionName): ?string
    {
        $insight = $audit->insights
            ->first(fn ($i) => strcasecmp($i->section_name, $sectionName) === 0);

        return $insight?->statement;
    }

    public function instruction(
        string $url,
        string $sectionName,
        string $element,
        string $original,
        string $critique,
        ?string $insight,
    ): string {
        $name = match ($element) {
            'headline' => 'headline',
            'subhead'  => 'supporting line',
            'cta'      => 'call-to-action button label',
        };

        $evidence = $insight
            ? "What this page's real numbers show about this section:\n{$insight}"
            : 'There is no measured insight for this section, so judge it on the critique alone.';

        $length = $element === 'cta'
            ? 'Keep every version to four words or fewer — it has to fit on a button.'
            : 'Keep every version under fifteen words.';

        return <<<TXT
        You are an experienced conversion copywriter improving one piece of copy on the
        landing page at {$url}.

        The section: {$sectionName}
        The {$name}, exactly as it appears on the page: "{$original}"

        What a UX reviewer said about this section:
        {$critique}

        {$evidence}

        Write two or three replacements. For each one, give a single-sentence reason that
        refers to the critique or the number above — not a general principle about good
        copywriting. If a number is available, name it in at least one reason.

        {$length}
        Write in the voice the page already uses. Do not invent product features,
        statistics, prices or guarantees that are not already in the original.
        TXT;
    }
}
