<?php

namespace App\Services\Correlation;

use App\Services\Correlation\Support\InsightCandidate;

/**
 * The rule that keeps the whole tool honest.
 *
 * An insight without a metric, a number and a section name is discarded — not
 * downgraded, not shown with a caveat. Discarded.
 *
 * It lives alone, in one method, with its own test, so it cannot be quietly
 * bypassed later by a rule that "just this once" wants to say something it
 * cannot back up. It is also what makes the product demonstrable: a person can
 * point at any line on screen and it will name a real number.
 */
class EvidenceGuarantee
{
    /**
     * @param  array<int,InsightCandidate>  $candidates
     * @return array<int,InsightCandidate>
     */
    public function filter(array $candidates): array
    {
        return array_values(array_filter($candidates, $this->canProveItself(...)));
    }

    public function canProveItself(InsightCandidate $candidate): bool
    {
        // A section name, so the reader knows where to look.
        if (trim($candidate->sectionName) === '') {
            return false;
        }

        // A metric, so the reader knows what was measured.
        $metric = $candidate->evidence['metric'] ?? null;
        if (! is_string($metric) || trim($metric) === '') {
            return false;
        }

        // A number, so the claim is checkable. Note array_key_exists rather than
        // isset: a value of exactly 0 is the strongest evidence there is, and
        // isset() would throw it away.
        if (! array_key_exists('value', $candidate->evidence)) {
            return false;
        }

        $value = $candidate->evidence['value'];

        // "quite low" is an opinion wearing a number.
        return is_int($value) || is_float($value);
    }
}
