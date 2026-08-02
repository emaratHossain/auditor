<?php

namespace App\Services\Correlation\Rules;

use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\InsightCandidate;
use App\Services\Correlation\Support\Section;

/**
 * A section sits below the point where most visitors stop scrolling.
 *
 * Built entirely on the position measured during capture, which is why that
 * measurement has to be honest — a wrong number here produces a confidently
 * wrong insight, and that is worse than no insight at all.
 */
class DropOffBeforeSection implements Rule
{
    /** Below this share of visitors reaching it, a section is effectively buried. */
    private const BURIED_BELOW = 0.50;

    public function key(): string
    {
        return 'drop_off_before_section';
    }

    public function evaluate(AuditSnapshot $snapshot): ?InsightCandidate
    {
        // Without scroll data this rule has nothing to stand on.
        if ($snapshot->metrics->sectionReach === []) {
            return null;
        }

        $worst = null;
        $worstReach = null;

        foreach ($snapshot->sections as $section) {
            // A section at the very top cannot be buried. A low number there means
            // something else is wrong, and this is not the rule to say what.
            if ($section->isAboveTheFold()) {
                continue;
            }

            $reach = $snapshot->metrics->reachFor($section->name);
            if ($reach === null || $reach >= self::BURIED_BELOW) {
                continue;
            }

            if ($worstReach === null || $reach < $worstReach) {
                $worst = $section;
                $worstReach = $reach;
            }
        }

        if ($worst === null) {
            return null;
        }

        return $this->describe($snapshot, $worst, $worstReach);
    }

    private function describe(AuditSnapshot $snapshot, Section $section, float $reach): InsightCandidate
    {
        $percent = round($reach * 100, 1);
        $above = $this->sectionsAbove($snapshot, $section);

        $because = $above === []
            ? 'It sits well down the page.'
            : sprintf(
                '%s %s above it, so most people never get this far.',
                $this->joinNames($above),
                count($above) === 1 ? 'sits' : 'sit',
            );

        return new InsightCandidate(
            ruleKey: $this->key(),
            sectionName: $section->name,
            statement: sprintf(
                'Only %s%% of visitors ever reach %s. %s Move it higher up, or put a second button earlier on the page.',
                $percent,
                $section->name,
                $because,
            ),
            evidence: [
                'metric'     => 'section_reach',
                'value'      => $percent,
                'unit'       => '%',
                'comparison' => sprintf('%s starts %s%% of the way down the page', $section->name, round($section->depth() * 100)),
            ],
            confidence: 0.85,
            severity: $percent < 25 ? 5 : 4,
            sourceProblem: $section->worstProblemIn('layout', 'content'),
        );
    }

    /** @return array<int,string> */
    private function sectionsAbove(AuditSnapshot $snapshot, Section $target): array
    {
        $names = [];

        foreach ($snapshot->sections as $section) {
            if ($section->position < $target->position && ! $section->isAboveTheFold()) {
                $names[] = $section->name;
            }
        }

        return $names;
    }

    private function joinNames(array $names): string
    {
        if (count($names) === 1) {
            return $names[0];
        }

        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }
}
