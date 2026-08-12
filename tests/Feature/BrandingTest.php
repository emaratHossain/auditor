<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_is_called_dropsense_ai(): void
    {
        $this->assertSame('DropSense AI', config('app.name'));
    }

    public function test_the_react_shell_says_dropsense_ai(): void
    {
        // The name lives in the wordmark rather than the shell, so it travels
        // with the component that draws it — the same reason the Conversion
        // Score label lives in the score readout below.
        $shell    = file_get_contents(resource_path('js/app.jsx'));
        $wordmark = file_get_contents(resource_path('js/features/report/Genie.jsx'));

        $this->assertStringContainsString('DropSense AI', $wordmark);
        $this->assertStringContainsString('Wordmark', $shell);

        $this->assertStringNotContainsString('Landing Page Auditor', $shell);
        $this->assertStringNotContainsString('Landing Page Auditor', $wordmark);
    }

    public function test_the_score_is_called_the_conversion_score_in_the_ui(): void
    {
        // It lives in the score readout rather than the page, so the label
        // travels with the component that renders the number.
        $ui  = file_get_contents(resource_path('js/features/report/ui.jsx'));
        $pdf = file_get_contents(resource_path('views/pdf/report.blade.php'));

        $this->assertStringContainsString('Conversion Score', $ui);
        $this->assertStringContainsString('Conversion Score', $pdf);
    }

    /** The rename is user-facing only. Renaming a working pipeline mid-week is churn. */
    public function test_the_code_keeps_its_v1_names(): void
    {
        $this->assertTrue(class_exists(\App\Services\HealthScorer::class));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('audits'));
    }
}
