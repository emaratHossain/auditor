<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuditRequest;
use App\Http\Resources\AuditReportResource;
use App\Http\Resources\AuditStatusResource;
use App\Models\Audit;
use App\Models\Page;
use App\Services\AuditService;

class AuditController extends Controller
{
    public function __construct(private AuditService $audits) {}

    /** Returns 201 immediately. The work happens on the queue. */
    public function store(StoreAuditRequest $request, Page $page)
    {
        $audit = $this->audits->start($page, $request->validated());

        return (new AuditStatusResource($audit))->response()->setStatusCode(201);
    }

    /**
     * The small payload the browser polls every five seconds.
     *
     * The poll is also where a stuck audit gets caught. No audit may sit on
     * pending or running forever, and the person watching the progress bar is
     * exactly who needs to be told why it is not moving.
     */
    public function show(Audit $audit)
    {
        $audit->failIfStalled();

        return new AuditStatusResource($audit);
    }

    /** Everything at once. */
    public function report(Audit $audit)
    {
        $audit->load(['page', 'metrics', 'sections', 'findings', 'insights', 'recommendations', 'rewrites']);

        return new AuditReportResource($audit);
    }

    /** Re-runs a failed audit with the numbers already on file. */
    public function retry(Audit $audit)
    {
        // Every field, not a subset. A whitelist that falls behind the form
        // silently drops numbers on retry, and the rules that needed them go
        // quiet without saying why.
        $metrics = $audit->metrics?->only([
            'visitors', 'bounce_rate', 'conversion_rate',
            'cta_click_rate', 'mobile_share', 'mobile_bounce_rate', 'section_reach',
            'rage_clicks', 'dead_clicks', 'source',
        ]) ?? [];

        $fresh = $this->audits->start($audit->page, $metrics);

        return (new AuditStatusResource($fresh))->response()->setStatusCode(201);
    }
}
