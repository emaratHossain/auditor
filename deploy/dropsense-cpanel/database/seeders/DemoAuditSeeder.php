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

        // The same file the endpoint serves, so what is shown on stage and what
        // ships in the seed cannot drift apart. The numbers are chosen there so
        // the story is unmistakable: almost everyone sees the button and almost
        // nobody presses it, the phone is far worse than the desktop, pricing is
        // buried, and one section collects rage clicks.
        $demo = config('demo-analytics');

        $audit->metrics()->create([
            'visitors'           => $demo['visitors'],
            'bounce_rate'        => $demo['bounce_rate'],
            'conversion_rate'    => $demo['conversion_rate'],
            'cta_click_rate'     => $demo['cta_click_rate'],
            'mobile_share'       => $demo['mobile_share'],
            'mobile_bounce_rate' => $demo['mobile_bounce_rate'],
            'section_reach'      => $demo['section_reach'],
            'rage_clicks'        => $demo['rage_clicks'],
            'dead_clicks'        => $demo['dead_clicks'],
            'source'             => 'demo',
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

        // Plausible scores for a page with exactly the problems this demo
        // describes. Present so the breakdown reads "measured" on stage rather
        // than quietly falling back to estimates.
        $lighthouse = [
            'performance'    => 64,
            'accessibility'  => 78,
            'best_practices' => 83,
            'seo'            => 92,
            'worst_checks'   => [
                ['id' => 'color-contrast', 'title' => 'Background and foreground colours do not have a sufficient contrast ratio', 'score' => 0],
                ['id' => 'largest-contentful-paint', 'title' => 'Largest Contentful Paint', 'score' => 41],
                ['id' => 'unused-javascript', 'title' => 'Reduce unused JavaScript', 'score' => 55],
            ],
        ];

        $findings = $audit->findings;
        $result = app(HealthScorer::class)->score([
            'cta'           => 34,
            'ux'            => 36,
            'ui'            => (int) round($findings->avg('ai_score')),
            'trust'         => 40,
            'performance'   => $lighthouse['performance'],
            'accessibility' => $lighthouse['accessibility'],
        ], measured: ['performance', 'accessibility']);

        $audit->update([
            'status'          => Audit::STATUS_COMPLETED,
            'stage'           => null,
            'overall_score'   => $result['overall'],
            'category_scores' => $result['categories'],
            'lighthouse'      => $lighthouse,
            'ai_model'        => 'stub',
            'completed_at'    => now(),
        ]);

        $this->storeRewrites($audit);

        $this->command?->info("Demo audit #{$audit->id} ready — score {$result['overall']}/100, ".$audit->recommendations()->count().' fixes.');
    }

    /**
     * The whole wifi insurance.
     *
     * These exist before anyone clicks, so a dead network on stage degrades the
     * rewrite panel to a labelled fallback rather than an error. The reasons
     * name the numbers from the fixture, because that is what the real rewriter
     * is asked to do and a seeded example that did less would be a lie about
     * what the feature produces.
     */
    private function storeRewrites(Audit $audit): void
    {
        $audit->rewrites()->create([
            'section_name' => 'Hero',
            'element'      => 'headline',
            'original'     => 'Welcome to our platform',
            'variants'     => [
                ['text' => 'Cut your reporting time from days to minutes', 'reason' => 'Names the outcome instead of greeting the visitor — and 96% of them reach this line, so it is the highest-leverage sentence on the page.'],
                ['text' => 'Every number your team argues about, in one place', 'reason' => 'Replaces a general claim with a specific problem a reader recognises.'],
                ['text' => 'Stop rebuilding the same report every Monday', 'reason' => 'Opens on the reader\'s frustration rather than the product, which suits a page with a 64% bounce rate.'],
            ],
            'model'  => 'seeded',
            'tokens' => 0,
        ]);

        $audit->rewrites()->create([
            'section_name' => 'Hero',
            'element'      => 'cta',
            'original'     => 'Submit',
            'variants'     => [
                ['text' => 'Start free', 'reason' => '96% of visitors reach this button and 2.1% press it — "Submit" describes the form, not what the visitor gets.'],
                ['text' => 'See your first report', 'reason' => 'Names the reward rather than the mechanism.'],
                ['text' => 'Try it on your data', 'reason' => 'Removes the sense of commitment that suppresses a first click.'],
            ],
            'model'  => 'seeded',
            'tokens' => 0,
        ]);
    }
}
