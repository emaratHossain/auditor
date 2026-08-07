<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Insight extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id', 'section_name', 'rule_key',
        'statement', 'evidence', 'confidence', 'severity',
    ];

    protected function casts(): array
    {
        return [
            'evidence'   => 'array',
            'confidence' => 'float',
            'severity'   => 'integer',
        ];
    }

    public function audit(): BelongsTo { return $this->belongsTo(Audit::class); }
}
