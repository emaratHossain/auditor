<?php

namespace App\Services;

use App\Jobs\AnalyzePageJob;
use App\Jobs\CaptureScreenshotsJob;
use App\Jobs\CorrelateJob;
use App\Jobs\RankAndScoreJob;
use App\Models\Audit;
use App\Models\Page;
use Illuminate\Support\Facades\Bus;
use Throwable;

class AuditService
{
    /**
     * Creates the audit and queues the four stages, then returns immediately.
     * Nobody waits on a spinner.
     */
    public function start(Page $page, array $metrics): Audit
    {
        $audit = $page->audits()->create(['status' => Audit::STATUS_PENDING]);

        $audit->metrics()->create([
            'visitors'           => $metrics['visitors'],
            'bounce_rate'        => $metrics['bounce_rate'],
            'conversion_rate'    => $metrics['conversion_rate'],
            'cta_click_rate'     => $metrics['cta_click_rate']     ?? null,
            'mobile_share'       => $metrics['mobile_share']       ?? null,
            'mobile_bounce_rate' => $metrics['mobile_bounce_rate'] ?? null,
            'section_reach'      => $metrics['section_reach']      ?? null,
            'source'             => 'manual',
        ]);

        $id = $audit->id;

        // A chain, not a batch: each stage needs the rows the one before it wrote,
        // and a failure has to stop everything after it. A half-finished audit
        // that looks complete is worse than one that says it failed.
        Bus::chain([
            new CaptureScreenshotsJob($id),
            new AnalyzePageJob($id),
            new CorrelateJob($id),
            new RankAndScoreJob($id),
        ])->catch(function (Throwable $e) use ($id) {
            Audit::find($id)?->markFailed(self::humanMessage($e));
        })->dispatch();

        return $audit->fresh();
    }

    /** A plain sentence the user can act on, never a stack trace. */
    public static function humanMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());

        if ($message === '' || str_contains($message, '::') || strlen($message) > 300) {
            return 'Something went wrong while auditing this page. Try again, and if it keeps failing check that the address is reachable.';
        }

        return $message;
    }
}
