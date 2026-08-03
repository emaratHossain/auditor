<?php

namespace Tests\Unit\Rewrite;

use App\Models\Audit;
use App\Models\Page;
use App\Services\Rewrite\RewriteService;
use App\Services\Rewrite\StubCopyRewriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RewriteServiceTest extends TestCase
{
    use RefreshDatabase;

    private function auditWithCopy(): Audit
    {
        $page = Page::create(['name' => 'Rewrite test', 'url' => 'https://example.com/rw']);
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

    private function service(): RewriteService
    {
        return new RewriteService(new StubCopyRewriter);
    }

    public function test_it_returns_versions_for_a_headline(): void
    {
        $rewrite = $this->service()->forElement($this->auditWithCopy(), 'Hero', 'headline');

        $this->assertSame('Welcome to our website', $rewrite->original);
        $this->assertGreaterThanOrEqual(2, count($rewrite->variants));
        $this->assertArrayHasKey('text', $rewrite->variants[0]);
        $this->assertArrayHasKey('reason', $rewrite->variants[0]);
    }

    public function test_it_rewrites_the_button_too(): void
    {
        $rewrite = $this->service()->forElement($this->auditWithCopy(), 'Hero', 'cta');

        $this->assertSame('Submit', $rewrite->original);
    }

    /** The second click must be free, or a demo rehearsal costs as much as the demo. */
    public function test_asking_twice_reuses_the_stored_row(): void
    {
        $audit = $this->auditWithCopy();
        $service = $this->service();

        $first  = $service->forElement($audit, 'Hero', 'headline');
        $second = $service->forElement($audit, 'Hero', 'headline');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $audit->rewrites()->count());
    }

    public function test_an_unknown_section_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nowhere');

        $this->service()->forElement($this->auditWithCopy(), 'Nowhere', 'headline');
    }

    public function test_an_unknown_element_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('footer');

        $this->service()->forElement($this->auditWithCopy(), 'Hero', 'footer');
    }

    public function test_a_section_with_no_words_of_that_kind_is_refused(): void
    {
        $audit = $this->auditWithCopy();
        $audit->sections()->create([
            'section_name'    => 'Bare',
            'viewport'        => 'desktop',
            'screenshot_path' => 'screenshots/1/bare.webp',
            'position'        => 900,
            'height'          => 600,
            'page_height'     => 4500,
            'sort_order'      => 1,
            'copy'            => ['headline' => null, 'subhead' => null, 'ctas' => []],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no headline');

        $this->service()->forElement($audit, 'Bare', 'headline');
    }

    /** Section names come off a real page; matching must not be case-brittle. */
    public function test_the_section_name_is_matched_case_insensitively(): void
    {
        $rewrite = $this->service()->forElement($this->auditWithCopy(), 'hero', 'headline');

        $this->assertSame('Hero', $rewrite->section_name);
    }
}
