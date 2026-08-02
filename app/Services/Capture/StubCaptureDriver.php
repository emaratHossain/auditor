<?php

namespace App\Services\Capture;

use App\Models\Audit;
use Illuminate\Support\Facades\Storage;

/**
 * Invents a believable page shape without opening a browser.
 *
 * Lets every stage after capture be built and demonstrated before Playwright is
 * wired up, and keeps the test suite free of a real browser.
 */
class StubCaptureDriver implements CaptureDriver
{
    private const SECTIONS = [
        ['Hero', 900],
        ['Features', 1200],
        ['Testimonials', 1000],
        ['Pricing', 1100],
        ['FAQ', 800],
        ['Footer', 500],
    ];

    public function capture(Audit $audit): int
    {
        $pageHeight = array_sum(array_column(self::SECTIONS, 1));
        $position = 0;
        $order = 0;

        foreach (self::SECTIONS as [$name, $height]) {
            foreach (['desktop', 'mobile'] as $viewport) {
                $path = $this->placeholder($audit->id, $name, $viewport);

                $audit->sections()->create([
                    'section_name'    => $name,
                    'viewport'        => $viewport,
                    'screenshot_path' => $path,
                    'position'        => $position,
                    'height'          => $height,
                    'page_height'     => $pageHeight,
                    'sort_order'      => $order,
                ]);
            }

            $position += $height;
            $order++;
        }

        return count(self::SECTIONS);
    }

    /** A labelled SVG so the report screen has something real to show. */
    private function placeholder(int $auditId, string $name, string $viewport): string
    {
        $w = $viewport === 'desktop' ? 1440 : 390;
        $h = $viewport === 'desktop' ? 400 : 700;

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
          <rect width="100%" height="100%" fill="#eef1ec"/>
          <rect x="8" y="8" width="{$w}" height="{$h}" fill="none" stroke="#c3c9be" stroke-width="2" stroke-dasharray="8 6" transform="translate(-8,-8)"/>
          <text x="50%" y="46%" text-anchor="middle" font-family="Georgia, serif" font-size="34" fill="#4a5450">{$name}</text>
          <text x="50%" y="56%" text-anchor="middle" font-family="monospace" font-size="15" fill="#77817c">{$viewport} · placeholder</text>
        </svg>
        SVG;

        $path = "screenshots/{$auditId}/".strtolower($name)."-{$viewport}.svg";
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
