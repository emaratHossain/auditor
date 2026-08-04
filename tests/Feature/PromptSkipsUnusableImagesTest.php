<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\Ai\PromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An empty screenshot file must never reach the model.
 *
 * Gemini answers a zero-byte image with a flat 400, "Unable to process input
 * image", which fails the whole audit — every other section, the numbers and the
 * Lighthouse run thrown away over one bad file. Capture is fixed not to write
 * one (see tests/e2e/capture-tall-mobile.spec.js), but a disk that filled up or
 * a killed process can produce the same thing, and the cost of being wrong is
 * the entire audit.
 *
 * Sending one image fewer is a far smaller loss than sending none.
 */
class PromptSkipsUnusableImagesTest extends TestCase
{
    use RefreshDatabase;

    private function audit(int $bytesInMobileShot): \App\Models\Audit
    {
        Storage::fake('public');

        $page = Page::create(['name' => 'Image test', 'url' => 'https://example.com/images']);
        $audit = $page->audits()->create(['status' => 'running']);

        // A real, non-empty desktop shot, and a phone shot whose size we control.
        Storage::disk('public')->put('shots/desktop.webp', str_repeat('x', 2048));
        Storage::disk('public')->put('shots/mobile.webp', str_repeat('x', $bytesInMobileShot));

        foreach ([['Section 1', 'desktop', 'shots/desktop.webp'], ['Section 1', 'mobile', 'shots/mobile.webp']] as $i => [$name, $viewport, $path]) {
            $audit->sections()->create([
                'section_name'    => $name,
                'viewport'        => $viewport,
                'screenshot_path' => $path,
                'position'        => 0,
                'height'          => 900,
                'page_height'     => 4200,
                'sort_order'      => $i,
            ]);
        }

        return $audit->fresh();
    }

    public function test_an_empty_screenshot_is_left_out_rather_than_sent(): void
    {
        $images = (new PromptBuilder)->images($this->audit(bytesInMobileShot: 0));

        $this->assertCount(1, $images, 'Only the desktop shot is usable.');
        $this->assertStringNotContainsString('phone', $images[0]['name']);
    }

    public function test_a_real_screenshot_is_still_sent(): void
    {
        $images = (new PromptBuilder)->images($this->audit(bytesInMobileShot: 2048));

        $this->assertCount(2, $images);
    }

    public function test_no_image_ever_goes_out_with_empty_data(): void
    {
        foreach ((new PromptBuilder)->images($this->audit(bytesInMobileShot: 0)) as $image) {
            $this->assertNotSame('', $image['data']);
        }
    }
}
