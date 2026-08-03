<?php

namespace App\Services\Capture;

use App\Models\Audit;
use App\Services\NodeBinary;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Drives scripts/capture.mjs and reads its JSON back.
 *
 * Laravel stays in charge; Node only drives the browser.
 */
class PlaywrightCaptureDriver implements CaptureDriver
{
    public function name(): string
    {
        return 'playwright';
    }

    public function capture(Audit $audit): int
    {
        $relative = "screenshots/{$audit->id}";
        $absolute = Storage::disk('public')->path($relative);

        $command = [
            NodeBinary::path(), base_path('scripts/capture.mjs'),
            '--url', $audit->page->url,
            '--out', $absolute,
        ];

        if ($selectors = $audit->page->section_selectors) {
            $command[] = '--selectors';
            $command[] = implode(',', $selectors);
        }

        $process = new Process($command, base_path(), null, null, 150);
        $process->run();

        $payload = json_decode(trim($process->getOutput()), true);

        if (! is_array($payload) || ($payload['ok'] ?? false) !== true) {
            throw new RuntimeException(
                'We could not open that page to photograph it. '.
                ($payload['error'] ?? 'Check the address is reachable from this machine.')
            );
        }

        $count = 0;

        foreach ($payload['sections'] as $section) {
            $audit->sections()->create([
                'section_name'    => $section['name'],
                'viewport'        => $section['viewport'],
                'screenshot_path' => $relative.'/'.basename($section['file']),
                'copy'            => $section['copy'] ?? null,
                'position'        => (int) $section['position'],
                'height'          => (int) $section['height'],
                'page_height'     => (int) $section['page_height'],
                'sort_order'      => (int) $section['sort_order'],
            ]);

            if ($section['viewport'] === 'desktop') {
                $count++;
            }
        }

        // Speed and accessibility are measured here because the browser is
        // already open — free at this point, and the score needs both.
        $audit->update([
            'lighthouse'      => $payload['lighthouse'] ?? null,
            'category_scores' => array_merge($audit->category_scores ?? [], [
                'load_ms'   => $payload['load_ms'] ?? null,
                'detection' => $payload['how'] ?? null,
            ]),
        ]);

        return $count;
    }
}
