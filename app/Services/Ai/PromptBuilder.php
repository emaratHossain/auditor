<?php

namespace App\Services\Ai;

use App\Models\Audit;
use App\Models\ScreenshotSection;
use Illuminate\Support\Facades\Storage;

/**
 * One instruction and one payload, shared by every driver.
 *
 * The clever bit of the whole product lives in the last paragraph: every picture
 * arrives with its own numbers attached, so the model is judging evidence rather
 * than offering a design opinion.
 */
class PromptBuilder
{
    /** Image types a vision model will actually accept. */
    private const RASTER = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    public function instruction(Audit $audit): string
    {
        $m = $audit->metrics;
        $url = $audit->page->url;

        $numbers = collect([
            'Visitors'                 => $m?->visitors,
            'Bounce rate'              => $m?->bounce_rate !== null ? $m->bounce_rate.'%' : null,
            'Conversion rate'          => $m?->conversion_rate !== null ? $m->conversion_rate.'%' : null,
            'Main button click rate'   => $m?->cta_click_rate !== null ? $m->cta_click_rate.'%' : 'not measured',
            'Share of visitors on a phone' => $m?->mobile_share !== null ? $m->mobile_share.'%' : 'not measured',
            'Bounce rate on a phone'   => $m?->mobile_bounce_rate !== null ? $m->mobile_bounce_rate.'%' : 'not measured',
        ])->map(fn ($v, $k) => "- {$k}: ".($v ?? 'not measured'))->implode("\n");

        $reach = collect($m?->section_reach ?? [])
            ->map(fn ($v, $k) => "- {$k}: {$v}% of visitors get this far")
            ->implode("\n") ?: '- not measured';

        $categories = implode(', ', AuditSchema::ALLOWED_CATEGORIES);

        return <<<TXT
        You are an experienced conversion, UI/UX and digital marketing expert reviewing a
        landing page at {$url}.

        You are given one image per section of the page, in the order a visitor meets them,
        plus that page's real visitor numbers.

        The page's numbers:
        {$numbers}

        How far down visitors actually get:
        {$reach}

        For each section, judge it using BOTH the picture and the numbers:
        - layout and visual hierarchy
        - the main call-to-action button: is it visible, large enough, high enough contrast, clearly worded
        - typography: readable sizes, sensible line length
        - design: colour consistency, image quality, how it holds up on a phone
        - trust: testimonials, customer logos, security badges, real proof
        - content: headline clarity, whether the value is stated, button wording

        Rules you must follow:
        - Be specific. Name the actual element you are talking about. "Improve the design"
          is useless; "the button's background is within one shade of the section behind it"
          is useful.
        - Say what to change, concretely enough that a designer could start today.
        - Where a number is marked "not measured", do NOT guess it and do NOT refer to it.
        - severity is 1 to 5, where 5 means this alone is probably costing conversions.
        - category must be exactly one of: {$categories}

        Reply with JSON only. No prose before or after it.
        TXT;
    }

    /** @return array<int,array{name:string,mime:string,data:string}> base64 images, top to bottom */
    public function images(Audit $audit): array
    {
        $images = [];

        foreach ($audit->sections()->where('viewport', 'desktop')->orderBy('sort_order')->get() as $section) {
            if ($encoded = $this->encode($section)) {
                $images[] = $encoded;
            }
        }

        // One phone shot so the model can judge the mobile layout it is asked about.
        $mobile = $audit->sections()->where('viewport', 'mobile')->orderBy('sort_order')->first();
        if ($mobile && $encoded = $this->encode($mobile)) {
            $encoded['name'] .= ' (on a phone)';
            $images[] = $encoded;
        }

        return $images;
    }

    private function encode(ScreenshotSection $section): ?array
    {
        $ext = strtolower(pathinfo($section->screenshot_path, PATHINFO_EXTENSION));

        // The stub driver writes SVG placeholders, which no vision model accepts.
        // Skipping them means AI_DRIVER=gemini with CAPTURE_DRIVER=stub degrades
        // to a text-only judgement rather than erroring.
        if (! isset(self::RASTER[$ext]) || ! Storage::disk('public')->exists($section->screenshot_path)) {
            return null;
        }

        return [
            'name' => $section->section_name,
            'mime' => self::RASTER[$ext],
            'data' => base64_encode(Storage::disk('public')->get($section->screenshot_path)),
        ];
    }
}
