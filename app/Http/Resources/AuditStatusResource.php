<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The small payload the browser polls every five seconds. */
class AuditStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'page_id'       => $this->page_id,
            'status'        => $this->status,
            'stage'         => $this->stage,
            'stage_label'   => $this->stageLabel(),
            'overall_score' => $this->overall_score,
            'error_message' => $this->error_message,
        ];
    }
}
