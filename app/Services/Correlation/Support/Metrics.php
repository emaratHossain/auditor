<?php

namespace App\Services\Correlation\Support;

/**
 * The seven numbers the user typed in, as a plain value object.
 *
 * The optional ones are nullable on purpose. A null switches off the rules that
 * need it — a rule must never read a missing number as a zero.
 */
readonly class Metrics
{
    public function __construct(
        public int $visitors,
        public float $bounceRate,
        public float $conversionRate,
        public ?float $ctaClickRate = null,
        public ?float $mobileShare = null,
        public ?float $mobileBounceRate = null,
        /** @var array<string,float> section name => % of visitors who reach it */
        public array $sectionReach = [],
    ) {}

    /** What share of visitors reach this section, as a fraction of 1, or null if unknown. */
    public function reachFor(string $sectionName): ?float
    {
        foreach ($this->sectionReach as $name => $percent) {
            if (strcasecmp((string) $name, $sectionName) === 0) {
                return (float) $percent / 100;
            }
        }

        return null;
    }
}
