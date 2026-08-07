<?php

namespace App\Services\Ai;

readonly class AnalysisResult
{
    public function __construct(
        /** @var array<int,array{section:string,score:int,problems:array}> */
        public array $sections,
        public string $model,
        public int $tokens = 0,
        public float $cost = 0.0,
        public ?array $raw = null,
    ) {}
}
