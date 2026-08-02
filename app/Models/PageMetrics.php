<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The seven numbers the user typed in.
 *
 * The optional ones are nullable on purpose. A null here switches off the rules
 * that need it — it must never be read as a zero or filled in with a default.
 */
class PageMetrics extends Model
{
    use HasFactory;

    protected $table = 'page_metrics';

    protected $fillable = [
        'audit_id', 'visitors', 'bounce_rate', 'conversion_rate',
        'cta_click_rate', 'mobile_share', 'mobile_bounce_rate',
        'section_reach', 'source',
    ];

    protected function casts(): array
    {
        return [
            'visitors'           => 'integer',
            'bounce_rate'        => 'float',
            'conversion_rate'    => 'float',
            'cta_click_rate'     => 'float',
            'mobile_share'       => 'float',
            'mobile_bounce_rate' => 'float',
            'section_reach'      => 'array',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /** How many visitors actually reach this section, as a share of 1. */
    public function reachFor(string $sectionName): ?float
    {
        $reach = $this->section_reach ?? [];

        foreach ($reach as $name => $percent) {
            if (strcasecmp($name, $sectionName) === 0) {
                return (float) $percent / 100;
            }
        }

        return null;
    }

    public function has(string $field): bool
    {
        return $this->{$field} !== null;
    }
}
