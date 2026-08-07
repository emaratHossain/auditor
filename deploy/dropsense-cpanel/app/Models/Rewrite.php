<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Two or three better versions of one piece of copy, with a reason for each.
 *
 * Stored rather than recomputed, so the second click is free, the PDF can carry
 * them, and the seeded demo page survives a dead network on stage.
 */
class Rewrite extends Model
{
    use HasFactory;

    public const ELEMENTS = ['headline', 'subhead', 'cta'];

    protected $fillable = [
        'audit_id', 'section_name', 'element', 'original', 'variants', 'model', 'tokens',
    ];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'tokens'   => 'integer',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
