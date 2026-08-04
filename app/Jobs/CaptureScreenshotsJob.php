<?php

namespace App\Jobs;

use App\Models\Audit;
use App\Services\Capture\CaptureDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CaptureScreenshotsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Capture is the slow stage; anything past this is wedged, not working.
     *
     * Measured on real landing pages with Lighthouse: 72-121 seconds. The old
     * 120s ceiling would have killed a Stripe-sized page a second before it
     * finished, which reads as a random failure rather than a timeout.
     */
    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public int $auditId) {}

    public function handle(CaptureDriver $driver): void
    {
        // The page this belongs to can be deleted while its chain is still
        // queued. That is a decision, not a failure — say nothing and stop.
        if (! $audit = Audit::find($this->auditId)) {
            return;
        }
        $audit->markStage('capturing');

        // Recorded before the work, so an audit that dies mid-capture still says
        // what was meant to be looking at the page.
        $audit->update(['capture_driver' => $driver->name()]);

        $count = $driver->capture($audit);

        if ($count === 0) {
            throw new \RuntimeException('We could not find any sections on that page to photograph.');
        }
    }
}
