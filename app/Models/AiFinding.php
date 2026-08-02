<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiFinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id', 'screenshot_section_id', 'section_name',
        'ai_score', 'problems', 'raw_response', 'model', 'tokens',
    ];

    protected function casts(): array
    {
        return [
            'problems'     => 'array',
            'raw_response' => 'array',
            'ai_score'     => 'integer',
            'tokens'       => 'integer',
        ];
    }

    public function audit(): BelongsTo   { return $this->belongsTo(Audit::class); }
    public function section(): BelongsTo { return $this->belongsTo(ScreenshotSection::class, 'screenshot_section_id'); }

    /** Problems the AI put in a given category, e.g. cta, trust, mobile. */
    public function problemsIn(string $category): array
    {
        return array_values(array_filter(
            $this->problems ?? [],
            fn ($p) => ($p['category'] ?? null) === $category
        ));
    }

    public function worstProblem(): ?array
    {
        $problems = $this->problems ?? [];
        if ($problems === []) {
            return null;
        }

        usort($problems, fn ($a, $b) => ($b['severity'] ?? 0) <=> ($a['severity'] ?? 0));

        return $problems[0];
    }
}
