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

    /**
     * How long an audit may sit before we call it stuck.
     *
     * Two different numbers because they are two different failures. Still
     * PENDING means no worker ever picked the job up — nothing is listening, and
     * no amount of waiting will change that. RUNNING means a stage started and
     * went quiet, which deserves far more rope: a real capture with Lighthouse
     * measured 72-121 seconds on live pages.
     */
    public const PENDING_TIMEOUT_SECONDS = 45;

    public const RUNNING_TIMEOUT_SECONDS = 600;

    /** What the progress bar says, in the order they happen. */
    public const STAGES = [
        'capturing'   => 'Taking pictures of the page',
        'analysing'   => 'The AI is looking at each section',
        'correlating' => 'Joining the numbers to the pictures',
        'scoring'     => 'Ranking the fixes and working out the score',
    ];

    protected $fillable = [
        'page_id', 'status', 'stage', 'overall_score', 'category_scores', 'lighthouse',
        'token_cost', 'ai_model', 'error_message', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'category_scores' => 'array',
            'lighthouse'      => 'array',
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
    public function rewrites(): HasMany          { return $this->hasMany(Rewrite::class); }

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

    /**
     * Why this audit is stuck, in words the person looking at it can act on —
     * or null if it is simply still working.
     *
     * The commonest cause by a distance is a forgotten queue worker. From the
     * outside that looks exactly like a slow audit, which is why the progress
     * bar alone is not honest enough.
     */
    public function stalledReason(): ?string
    {
        if (! in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true)) {
            return null;
        }

        $idleFor = $this->updated_at?->diffInSeconds(now()) ?? 0;

        if ($this->status === self::STATUS_PENDING && $idleFor > self::PENDING_TIMEOUT_SECONDS) {
            return 'This audit was queued but nothing picked it up, which almost always means no queue worker is running. '
                .'Start one with: php artisan queue:work';
        }

        if ($this->status === self::STATUS_RUNNING && $idleFor > self::RUNNING_TIMEOUT_SECONDS) {
            return 'This audit started and then stopped responding partway through'
                .($this->stageLabel() ? ' — it was on "'.$this->stageLabel().'"' : '')
                .'. Try again, and if it keeps happening check that the page is reachable from this machine.';
        }

        return null;
    }

    /**
     * Fails a stuck audit so the screen can say what happened.
     *
     * Checked lazily when someone polls, on purpose: needing a second background
     * process to notice that a background process is missing would be absurd.
     */
    public function failIfStalled(): void
    {
        if ($reason = $this->stalledReason()) {
            $this->markFailed($reason);
        }
    }
}
