<?php

namespace App\Jobs;

use App\Models\Audit;
use App\Services\Capture\CaptureDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CaptureScreenshotsJob implements ShouldQueue
{
    use Queueable;

    /** Capture is the slow stage; anything past this is wedged, not working. */
    public int $timeout = 120;
    public int $tries = 1;

    public function __construct(public int $auditId) {}

    public function handle(CaptureDriver $driver): void
    {
        $audit = Audit::findOrFail($this->auditId);
        $audit->markStage('capturing');

        $count = $driver->capture($audit);

        if ($count === 0) {
            throw new \RuntimeException('We could not find any sections on that page to photograph.');
        }
    }
}
