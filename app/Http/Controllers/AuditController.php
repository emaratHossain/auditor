<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuditRequest;
use App\Http\Resources\AuditReportResource;
use App\Http\Resources\AuditStatusResource;
use App\Models\Audit;
use App\Models\Page;
use App\Services\AuditService;
use InvalidArgumentException;

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

    /**
     * Re-runs a failed audit with the numbers already on file.
     *
     * This is the button on every failure screen, so it is the one button that
     * must never itself fail — a 500 here leaves the user at a dead end on the
     * page that was already telling them something went wrong.
     */
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

        try {
            $fresh = $this->audits->start($audit->page, $metrics);
        } catch (InvalidArgumentException $e) {
            // No numbers were ever saved against this audit, so there is nothing
            // to re-run it with — and inventing them is the one thing this
            // product must never do.
            return response()->json([
                'message' => 'We do not have the numbers that audit was run with, so it cannot be repeated. '
                    .'Start a new audit for this page from the pages screen.',
            ], 422);
        }

        return (new AuditStatusResource($fresh))->response()->setStatusCode(201);
    }
}
