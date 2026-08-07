<?php

namespace App\Services\Correlation\Support;

/** One section of the page, with what the AI said about it. */
readonly class Section
{
    public function __construct(
        public string $name,
        public int $position,
        public int $height,
        public int $pageHeight,
        public int $aiScore,
        /** @var array<int,array{what:string,why:string,fix:string,severity:int,category:string}> */
        public array $problems = [],
    ) {}

    /** How far down the page this section starts, as a fraction of 1. */
    public function depth(): float
    {
        return $this->pageHeight > 0 ? $this->position / $this->pageHeight : 0.0;
    }

    /** 900px is the desktop viewport height used during capture. */
    public function isAboveTheFold(): bool
    {
        return $this->position < 900;
    }

    /** @return array<int,array<string,mixed>> */
    public function problemsIn(string ...$categories): array
    {
        return array_values(array_filter(
            $this->problems,
            fn (array $p) => in_array($p['category'] ?? '', $categories, true)
        ));
    }

    public function worstProblemIn(string ...$categories): ?array
    {
        $problems = $this->problemsIn(...$categories);
        if ($problems === []) {
            return null;
        }

        usort($problems, fn ($a, $b) => ($b['severity'] ?? 0) <=> ($a['severity'] ?? 0));

        return $problems[0];
    }
}
