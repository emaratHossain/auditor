<?php

namespace App\Services\Ai;

use App\Models\Audit;

/**
 * One request per audit, containing every section picture, the visitor numbers
 * and the section positions — not one request per section.
 *
 * The original plan sent thirteen. Batching sends the instruction once instead
 * of thirteen times, costs roughly a quarter as much, AND gives the model the
 * whole-page view that the separate correlation request existed to buy.
 */
interface VisionAnalyzer
{
    /**
     * @return AnalysisResult validated against AuditSchema before it is returned
     */
    public function analyse(Audit $audit): AnalysisResult;

    public function modelName(): string;
}
