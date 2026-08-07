<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Resource is the only place that decides what the browser sees, so adding a
 * database column can never leak it into the API by accident.
 */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latest = $this->whenLoaded('audits', fn () => $this->audits->first(), fn () => $this->latestAudit());

        return [
            'id'   => $this->id,
            'name' => $this->name,
            'url'  => $this->url,
            // What the delete confirmation counts. Absent unless index asked
            // for it, and 0 is a truthful answer for a page never audited.
            'audits_count' => $this->whenCounted('audits'),
            'latest_audit' => $latest ? [
                'id'      => $latest->id,
                'status'  => $latest->status,
                'score'   => $latest->overall_score,
                'ran_at'  => $latest->created_at?->toIso8601String(),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
