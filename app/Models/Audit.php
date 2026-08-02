<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Audit extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    /** What the progress bar says, in the order they happen. */
    public const STAGES = [
        'capturing'   => 'Taking pictures of the page',
        'analysing'   => 'The AI is looking at each section',
        'correlating' => 'Joining the numbers to the pictures',
        'scoring'     => 'Ranking the fixes and working out the score',
    ];

    protected $fillable = [
        'page_id', 'status', 'stage', 'overall_score', 'category_scores',
        'token_cost', 'ai_model', 'error_message', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'category_scores' => 'array',
            'token_cost'      => 'float',
            'completed_at'    => 'datetime',
        ];
    }

    public function page(): BelongsTo            { return $this->belongsTo(Page::class); }
    public function metrics(): HasOne            { return $this->hasOne(PageMetrics::class); }
    public function sections(): HasMany          { return $this->hasMany(ScreenshotSection::class)->orderBy('sort_order'); }
    public function findings(): HasMany          { return $this->hasMany(AiFinding::class); }
    public function insights(): HasMany          { return $this->hasMany(Insight::class); }
    public function recommendations(): HasMany   { return $this->hasMany(Recommendation::class)->orderByDesc('priority_score'); }

    public function markStage(string $stage): void
    {
        $this->update(['status' => self::STATUS_RUNNING, 'stage' => $stage]);
    }

    /** A plain sentence for the user, never a stack trace. */
    public function markFailed(string $message): void
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }

    public function stageLabel(): ?string
    {
        return $this->stage ? (self::STAGES[$this->stage] ?? $this->stage) : null;
    }
}
