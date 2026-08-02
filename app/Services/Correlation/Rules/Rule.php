<?php

namespace App\Services\Correlation\Rules;

use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\InsightCandidate;

interface Rule
{
    public function key(): string;

    /** Return null when this rule has nothing to say, or lacks the numbers to say it. */
    public function evaluate(AuditSnapshot $snapshot): ?InsightCandidate;
}
