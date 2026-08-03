<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoMetricsEndpointTest extends TestCase
{
    public function test_it_serves_every_field_the_form_needs(): void
    {
        $this->getJson('/api/demo-metrics')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'label', 'source',
                'visitors', 'bounce_rate', 'conversion_rate',
                'cta_click_rate', 'mobile_share', 'mobile_bounce_rate',
                'section_reach', 'rage_clicks', 'dead_clicks',
            ]]);
    }

    public function test_it_is_labelled_as_demo_data(): void
    {
        $this->getJson('/api/demo-metrics')
            ->assertJsonPath('data.source', 'demo');
    }

    /**
     * The numbers must tell an unmistakable story on stage: almost everyone sees
     * the button and almost nobody presses it, the phone is far worse than the
     * desktop, pricing is buried, and one section collects rage clicks.
     */
    public function test_the_numbers_tell_the_demo_story(): void
    {
        $d = config('demo-analytics');

        $this->assertLessThan(5.0, $d['cta_click_rate'], 'the button must look ignored');
        $this->assertGreaterThan(80.0, $d['section_reach']['Hero'], 'nearly everyone must reach the hero');
        $this->assertLessThan(30.0, $d['section_reach']['Pricing'], 'pricing must be buried');
        $this->assertGreaterThan(
            $d['bounce_rate'] + 10,
            $d['mobile_bounce_rate'],
            'the phone must be materially worse than the desktop',
        );
        $this->assertGreaterThan(200, max($d['rage_clicks']), 'one section must collect rage clicks');
    }

    /** The endpoint and the seeder must read the same file, or the stage and the seed drift. */
    public function test_the_endpoint_returns_exactly_what_the_config_holds(): void
    {
        // Assert the config is populated first, or this passes vacuously with
        // null === null and proves nothing at all.
        $this->assertIsInt(config('demo-analytics.visitors'));

        $this->getJson('/api/demo-metrics')
            ->assertJsonPath('data.visitors', config('demo-analytics.visitors'));
    }
}
