<?php

namespace App\Services\Correlation\Support;

/**
 * Everything a rule needs, with no database and no Eloquent in sight — which is
 * exactly why the rules can be unit-tested in milliseconds.
 */
readonly class AuditSnapshot
{
    public function __construct(
        public Metrics $metrics,
        /** @var array<int,Section> ordered top to bottom */
        public array $sections = [],
    ) {}

    public function section(string $name): ?Section
    {
        foreach ($this->sections as $section) {
            if (strcasecmp($section->name, $name) === 0) {
                return $section;
            }
        }

        return null;
    }

    /** The section most likely to hold the main button, judged by what the AI flagged. */
    public function sectionWithCta(): ?Section
    {
        foreach ($this->sections as $section) {
            if ($section->problemsIn('cta') !== []) {
                return $section;
            }
        }

        return $this->sections[0] ?? null;
    }

    /** @return array<int,Section> */
    public function sectionsAboveTheFold(): array
    {
        return array_values(array_filter($this->sections, fn (Section $s) => $s->isAboveTheFold()));
    }
}
