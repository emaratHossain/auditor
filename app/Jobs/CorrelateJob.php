<?php

namespace App\Jobs;

use App\Models\Audit;
use App\Services\Correlation\CorrelationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CorrelateJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(public int $auditId) {}

    public function handle(CorrelationService $correlation): void
    {
        $audit = Audit::findOrFail($this->auditId);
        $audit->markStage('correlating');

        // Zero insights is a legitimate outcome, not a failure. A page where
        // nothing can be proven should say nothing rather than invent something.
        $correlation->correlate($audit);
    }
}
