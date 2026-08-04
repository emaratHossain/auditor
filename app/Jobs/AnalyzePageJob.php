<?php

namespace App\Jobs;

use App\Models\Audit;
use App\Services\Ai\AuditSchema;
use App\Services\Ai\SectionMatcher;
use App\Services\Ai\VisionAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * ONE request for the whole page — every section picture, the numbers, and the
 * section positions together. Not one request per section.
 */
class AnalyzePageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(public int $auditId) {}

    public function handle(VisionAnalyzer $analyzer, AuditSchema $schema): void
    {
        $audit = Audit::findOrFail($this->auditId);
        $audit->markStage('analysing');

        $result = $this->analyseWithOneRetry($analyzer, $audit, $schema);

        $ceiling = (float) config('ai.max_cost_per_audit', 0.25);
        if ($result->cost > $ceiling) {
            throw new \RuntimeException(sprintf(
                'This audit would have cost $%.4f, which is over the $%.2f ceiling. Nothing was saved.',
                $result->cost, $ceiling
            ));
        }

        $sections = $audit->sections()->where('viewport', 'desktop')->get()->keyBy(fn ($s) => $s->section_name);
        $matcher = new SectionMatcher($sections->keys()->all());
        $unmatched = [];

        foreach ($result->sections as $section) {
            $name = $matcher->match($section['section']);
            $match = $name === null ? null : $sections->get($name);

            if (! $match) {
                $unmatched[] = $section['section'];
            }

            $audit->findings()->create([
                'screenshot_section_id' => $match?->id,
                'section_name'          => $section['section'],
                'ai_score'              => $section['score'],
                'problems'              => $section['problems'],
                'raw_response'          => $result->raw,
                'model'                 => $result->model,
                'tokens'                => $result->tokens,
            ]);
        }

        if ($unmatched !== []) {
            // Not fatal — the rest of the audit is still worth having — but it is
            // the one failure that otherwise produces an empty report in silence.
            Log::warning('AI named sections that were never captured, so their findings cannot be evidenced.', [
                'audit_id'  => $audit->id,
                'model'     => $result->model,
                'unmatched' => $unmatched,
                'captured'  => $sections->keys()->all(),
            ]);
        }

        $audit->update([
            'token_cost'         => $result->cost,
            'ai_model'           => $result->model,
            'unmatched_findings' => count($unmatched),
        ]);
    }

    /**
     * A vision model will eventually reply off-shape. Retry once, then fail
     * cleanly — never save half-parsed text.
     */
    private function analyseWithOneRetry(VisionAnalyzer $analyzer, Audit $audit, AuditSchema $schema)
    {
        foreach ([1, 2] as $attempt) {
            try {
                $result = $analyzer->analyse($audit);
                $schema->validate(['sections' => $result->sections]);

                return $result;
            } catch (InvalidArgumentException $e) {
                if ($attempt === 2) {
                    throw new \RuntimeException(
                        'The AI replied in an unexpected format twice in a row, so nothing was saved. ('.$e->getMessage().')'
                    );
                }
            }
        }
    }
}
