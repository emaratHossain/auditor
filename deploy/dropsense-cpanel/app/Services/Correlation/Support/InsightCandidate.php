<?php

namespace App\Services\Correlation\Support;

/**
 * What a rule produces before the evidence guarantee has had its say.
 *
 * It is only a *candidate* because CorrelationService still throws away anything
 * that cannot name a metric, a number and a section.
 */
readonly class InsightCandidate
{
    public function __construct(
        public string $ruleKey,
        public string $sectionName,
        public string $statement,
        /** @var array{metric:string,value:float,unit:string,comparison?:string} */
        public array $evidence,
        public float $confidence,
        public int $severity,
        public ?array $sourceProblem = null,
    ) {}
}
