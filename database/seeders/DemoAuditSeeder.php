<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Services\Correlation\CorrelationService;
use App\Services\Capture\StubCaptureDriver;
use App\Services\HealthScorer;
use App\Services\RecommendationEngine;
use App\Services\Ai\AnalysisResult;
use App\Services\Ai\StubVisionAnalyzer;
use App\Models\Audit;
use Illuminate\Database\Seeder;

/**
 * One realistic, finished audit that always works.
 *
 * The demo must never depend on a live network, a reachable URL and Chromium
 * all behaving at once in front of an audience. This is the floor under that.
 *
 * It is a seeder, not a feature — nothing in the app mentions it.
 *
 *   php artisan db:seed --class=DemoAuditSeeder
 */
class DemoAuditSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::firstOrCreate(
            ['url' => 'https://demo.example.com/pricing-launch'],
            ['name' => 'Spring launch — pricing page'],
        );

        $audit = $page->audits()->create(['status' => Audit::STATUS_RUNNING, 'stage' => 'capturing']);

        // Numbers chosen so the story is unmistakable on stage: almost everyone
        // sees the button and almost nobody presses it, the phone is far worse
        // than the desktop, and pricing is buried near the bottom.
        $audit->metrics()->create([
            'visitors'           => 18_450,
            'bounce_rate'        => 64.0,
            'conversion_rate'    => 1.6,
            'cta_click_rate'     => 2.1,
            'mobile_share'       => 71.0,
            'mobile_bounce_rate' => 81.0,
            'section_reach'      => ['Hero' => 97, 'Features' => 68, 'Testimonials' => 41, 'Pricing' => 19, 'FAQ' => 12, 'Footer' => 9],
            'source'             => 'manual',
        ]);

        (new StubCaptureDriver)->capture($audit);

        $analysis = (new StubVisionAnalyzer)->analyse($audit->fresh());
        $sections = $audit->sections()->where('viewport', 'desktop')->get()->keyBy(fn ($s) => strtolower($s->section_name));

        foreach ($analysis->sections as $section) {
            $audit->findings()->create([
                'screenshot_section_id' => $sections->get(strtolower($section['section']))?->id,
                'section_name'          => $section['section'],
                'ai_score'              => $section['score'],
                'problems'              => $section['problems'],
                'raw_response'          => $analysis->raw,
                'model'                 => $analysis->model,
                'tokens'                => 0,
            ]);
        }

        $audit->refresh();
        app(CorrelationService::class)->correlate($audit);
        app(RecommendationEngine::class)->generate($audit->fresh());

        $findings = $audit->findings;
        $result = app(HealthScorer::class)->score([
            'cta'           => 34,
            'ux'            => 36,
            'ui'            => (int) round($findings->avg('ai_score')),
            'trust'         => 40,
            'performance'   => 74,
            'accessibility' => 71,
        ]);

        $audit->update([
            'status'          => Audit::STATUS_COMPLETED,
            'stage'           => null,
            'overall_score'   => $result['overall'],
            'category_scores' => $result['categories'],
            'ai_model'        => 'stub',
            'completed_at'    => now(),
        ]);

        $this->command?->info("Demo audit #{$audit->id} ready — score {$result['overall']}/100, ".$audit->recommendations()->count().' fixes.');
    }
}
