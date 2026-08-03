<?php

namespace Tests\Unit\Correlation;

use App\Services\Correlation\Rules\RageClickMismatch;
use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\Metrics;
use App\Services\Correlation\Support\Section;
use PHPUnit\Framework\TestCase;

class RageClickMismatchTest extends TestCase
{
    private function snapshot(
        ?array $rageClicks,
        float $ctaClickRate = 2.1,
        ?array $problems = null,
    ): AuditSnapshot {
        return new AuditSnapshot(
            metrics: new Metrics(
                visitors: 18_450,
                bounceRate: 64.2,
                conversionRate: 1.8,
                ctaClickRate: $ctaClickRate,
                sectionReach: ['Features' => 71.0],
                rageClicks: $rageClicks ?? [],
                deadClicks: $rageClicks === null ? [] : ['Features' => 512],
            ),
            sections: [
                new Section(
                    name: 'Features',
                    position: 1200,
                    height: 900,
                    pageHeight: 4500,
                    aiScore: 58,
                    problems: $problems ?? [[
                        'what'     => 'The feature cards lift on hover but nothing happens when you click them.',
                        'why'      => 'A hover effect is a promise that the thing is clickable.',
                        'fix'      => 'Either link the cards or remove the hover effect.',
                        'severity' => 4,
                        'category' => 'ui',
                    ]],
                ),
            ],
        );
    }

    public function test_it_fires_when_a_section_collects_rage_clicks_and_the_button_is_ignored(): void
    {
        $candidate = (new RageClickMismatch)->evaluate($this->snapshot(['Features' => 340]));

        $this->assertNotNull($candidate);
        $this->assertSame('Features', $candidate->sectionName);
        $this->assertSame('rage_clicks', $candidate->evidence['metric']);
        $this->assertSame(340.0, (float) $candidate->evidence['value']);
        $this->assertStringContainsString('340', $candidate->statement);
    }

    /** Both halves are required. Rage clicks on a page whose button works is a different story. */
    public function test_it_stays_quiet_when_the_button_is_doing_fine(): void
    {
        $this->assertNull(
            (new RageClickMismatch)->evaluate($this->snapshot(['Features' => 340], ctaClickRate: 24.0))
        );
    }

    public function test_a_handful_of_rage_clicks_is_not_a_pattern(): void
    {
        $this->assertNull((new RageClickMismatch)->evaluate($this->snapshot(['Features' => 3])));
    }

    /** A null must never be read as a zero — and must never be read as a signal either. */
    public function test_no_rage_click_data_keeps_it_silent(): void
    {
        $this->assertNull((new RageClickMismatch)->evaluate($this->snapshot(null)));
    }

    public function test_it_needs_the_ai_to_have_seen_something_visual_too(): void
    {
        $this->assertNull(
            (new RageClickMismatch)->evaluate($this->snapshot(['Features' => 340], problems: []))
        );
    }

    /** A blank click rate switches the rule off, exactly like the other four. */
    public function test_a_blank_button_click_rate_keeps_it_silent(): void
    {
        $snapshot = new AuditSnapshot(
            metrics: new Metrics(
                visitors: 18_450,
                bounceRate: 64.2,
                conversionRate: 1.8,
                ctaClickRate: null,
                rageClicks: ['Features' => 340],
            ),
            sections: [
                new Section(
                    name: 'Features', position: 1200, height: 900, pageHeight: 4500, aiScore: 58,
                    problems: [['what' => 'x', 'why' => 'y', 'fix' => 'z', 'severity' => 4, 'category' => 'ui']],
                ),
            ],
        );

        $this->assertNull((new RageClickMismatch)->evaluate($snapshot));
    }
}
