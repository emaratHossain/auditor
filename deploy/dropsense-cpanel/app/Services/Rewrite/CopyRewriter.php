<?php

namespace App\Services\Rewrite;

use App\Models\Audit;

/**
 * One text-only call, made on click rather than during the audit.
 *
 * In the pipeline it would be billed on every audit whether or not anyone reads
 * it — and it would turn the demo's live moment into a reveal of something
 * prepared earlier.
 */
interface CopyRewriter
{
    /**
     * @param  string  $critique  what the vision pass said about this section
     * @param  string|null  $insight  the correlation insight, if one attached to this section
     */
    public function rewrite(
        Audit $audit,
        string $sectionName,
        string $element,
        string $original,
        string $critique,
        ?string $insight,
    ): RewriteResult;

    public function modelName(): string;
}
