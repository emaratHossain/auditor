<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRewriteRequest;
use App\Models\Audit;
use App\Services\Rewrite\RewriteService;
use InvalidArgumentException;

class RewriteController extends Controller
{
    public function __construct(private RewriteService $rewrites) {}

    public function __invoke(StoreRewriteRequest $request, Audit $audit)
    {
        // The prompt needs the critique and the insight, so both come along.
        $audit->load(['page', 'findings', 'insights']);

        try {
            $rewrite = $this->rewrites->forElement(
                $audit,
                $request->string('section')->toString(),
                $request->string('element')->toString(),
            );
        } catch (InvalidArgumentException $e) {
            // The section or element does not exist on this page. That is a bad
            // request, not a server fault, and the message is already written
            // for a human to read.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Never return the model. An explicit array is the only place that
        // decides what React sees.
        return response()->json(['data' => [
            'id'       => $rewrite->id,
            'section'  => $rewrite->section_name,
            'element'  => $rewrite->element,
            'original' => $rewrite->original,
            'variants' => $rewrite->variants,
            'model'    => $rewrite->model,
        ]], 201);
    }
}
