<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenshotSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id', 'section_name', 'viewport', 'screenshot_path',
        'position', 'height', 'page_height', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'position'    => 'integer',
            'height'      => 'integer',
            'page_height' => 'integer',
            'sort_order'  => 'integer',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * How far down the page this section starts, as a share of 1.
     * 0.0 is the very top; 0.8 means you must scroll through 80% to reach it.
     */
    public function depth(): float
    {
        return $this->page_height > 0
            ? round($this->position / $this->page_height, 4)
            : 0.0;
    }

    public function isAboveTheFold(): bool
    {
        // 900px is the desktop viewport height used during capture.
        return $this->position < 900;
    }
}
