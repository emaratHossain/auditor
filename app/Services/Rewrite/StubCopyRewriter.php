<?php

namespace App\Services\Rewrite;

use App\Models\Audit;

/**
 * Costs nothing, needs no network, and is deliberately NOT random — the same
 * copy always produces the same versions, so a rehearsal matches the demo.
 *
 * This is also the build-week default and the safety net if the venue network
 * dies: set AI_REWRITE_DRIVER=stub and the button still works.
 */
class StubCopyRewriter implements CopyRewriter
{
    public function modelName(): string
    {
        return 'stub';
    }

    public function rewrite(
        Audit $audit,
        string $sectionName,
        string $element,
        string $original,
        string $critique,
        ?string $insight,
    ): RewriteResult {
        // The stub cannot write good copy, but it CAN prove the wiring: that the
        // critique and the insight reached this method, and that a reason which
        // cites evidence is what comes back.
        $evidence = $insight ? ' It answers what the numbers show on this section.' : '';

        return new RewriteResult(
            variants: [
                [
                    'text'   => $this->outcomeLed($original, $element),
                    'reason' => 'Leads with the outcome instead of describing the product.'.$evidence,
                ],
                [
                    'text'   => $this->specific($original, $element),
                    'reason' => 'Replaces a general claim with something a reader can picture.',
                ],
                [
                    'text'   => $this->direct($original, $element),
                    'reason' => 'Shorter and more direct, which suits a first-time visitor who is scanning.',
                ],
            ],
            model: $this->modelName(),
            tokens: 0,
        );
    }

    private function outcomeLed(string $original, string $element): string
    {
        return $element === 'cta'
            ? 'Start free — no card needed'
            : 'Get '.lcfirst(rtrim($original, '.')).' working by Friday';
    }

    private function specific(string $original, string $element): string
    {
        return $element === 'cta'
            ? 'See it on your own page'
            : rtrim($original, '.').' — in about ten minutes';
    }

    private function direct(string $original, string $element): string
    {
        return $element === 'cta'
            ? 'Try it free'
            : ucfirst(strtok(rtrim($original, '.'), ',') ?: $original);
    }
}
