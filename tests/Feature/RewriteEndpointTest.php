<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewriteEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function audit(): Audit
    {
        $page = Page::create(['name' => 'Endpoint test', 'url' => 'https://example.com/ep']);
        $audit = $page->audits()->create(['status' => Audit::STATUS_COMPLETED]);

        $audit->sections()->create([
            'section_name'    => 'Hero',
            'viewport'        => 'desktop',
            'screenshot_path' => 'screenshots/1/hero.webp',
            'position'        => 0,
            'height'          => 900,
            'page_height'     => 4500,
            'sort_order'      => 0,
            'copy'            => [
                'headline' => ['text' => 'Welcome to our website', 'tag' => 'h1', 'selector' => 'h1'],
                'subhead'  => ['text' => 'We do things.', 'tag' => 'p', 'selector' => 'p'],
                'ctas'     => [['text' => 'Submit', 'tag' => 'button', 'selector' => 'button']],
            ],
        ]);

        return $audit;
    }

    public function test_it_returns_versions_with_reasons(): void
    {
        $audit = $this->audit();

        $this->postJson("/api/audits/{$audit->id}/rewrite", [
            'section' => 'Hero',
            'element' => 'headline',
        ])
            ->assertCreated()
            ->assertJsonPath('data.original', 'Welcome to our website')
            ->assertJsonStructure(['data' => ['id', 'section', 'element', 'original', 'model', 'variants' => [['text', 'reason']]]]);
    }

    public function test_asking_twice_does_not_call_the_model_again(): void
    {
        $audit = $this->audit();
        $body = ['section' => 'Hero', 'element' => 'headline'];

        $first  = $this->postJson("/api/audits/{$audit->id}/rewrite", $body)->assertCreated();
        $second = $this->postJson("/api/audits/{$audit->id}/rewrite", $body)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, $audit->rewrites()->count());
    }

    public function test_an_unknown_section_returns_422_not_a_stack_trace(): void
    {
        $audit = $this->audit();

        $this->postJson("/api/audits/{$audit->id}/rewrite", [
            'section' => 'Nowhere',
            'element' => 'headline',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_an_unknown_element_is_rejected_by_validation(): void
    {
        $audit = $this->audit();

        $this->postJson("/api/audits/{$audit->id}/rewrite", [
            'section' => 'Hero',
            'element' => 'footer',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('element');
    }

    public function test_stored_rewrites_ride_along_in_the_report(): void
    {
        $audit = $this->audit();

        $this->postJson("/api/audits/{$audit->id}/rewrite", ['section' => 'Hero', 'element' => 'cta']);

        $this->getJson("/api/audits/{$audit->id}/report")
            ->assertOk()
            ->assertJsonPath('data.rewrites.0.element', 'cta')
            ->assertJsonPath('data.rewrites.0.original', 'Submit');
    }

    public function test_a_report_with_no_rewrites_yet_returns_an_empty_list(): void
    {
        $audit = $this->audit();

        $this->getJson("/api/audits/{$audit->id}/report")
            ->assertOk()
            ->assertJsonPath('data.rewrites', []);
    }
}
