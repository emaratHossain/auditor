<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    use HasFactory;

    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_LOW    = 'low';

    protected $fillable = [
        'audit_id', 'insight_id', 'section_name',
        'title', 'evidence', 'suggested_fix', 'expected_impact',
        'priority', 'priority_score', 'effort', 'severity',
        'traffic_share', 'confidence',
    ];

    protected function casts(): array
    {
        return [
            'priority_score' => 'float',
            'traffic_share'  => 'float',
            'confidence'     => 'float',
            'effort'         => 'integer',
            'severity'       => 'integer',
        ];
    }

    public function audit(): BelongsTo   { return $this->belongsTo(Audit::class); }
    public function insight(): BelongsTo { return $this->belongsTo(Insight::class); }
}
