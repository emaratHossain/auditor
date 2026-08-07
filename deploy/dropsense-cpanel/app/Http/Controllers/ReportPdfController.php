<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Services\NodeBinary;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class ReportPdfController extends Controller
{
    public function __invoke(Audit $audit)
    {
        abort_unless($audit->status === Audit::STATUS_COMPLETED, 409, 'That audit has not finished yet.');

        $audit->load(['page', 'sections', 'findings', 'recommendations', 'rewrites']);

        $findings = $audit->findings->keyBy(fn ($f) => strtolower($f->section_name));

        // Images are embedded as data URIs so the PDF is self-contained and does
        // not depend on a signed URL that will expire.
        $sections = $audit->sections->where('viewport', 'desktop')->values()->map(function ($s) use ($findings) {
            $finding = $findings->get(strtolower($s->section_name));

            return [
                'name'             => $s->section_name,
                'position_percent' => (int) round($s->depth() * 100),
                'score'            => $finding?->ai_score,
                'problems'         => $finding?->problems ?? [],
                'image'            => $this->dataUri($s->screenshot_path),
            ];
        })->all();

        // Keyed by section so the view can drop them beside the right picture.
        $rewrites = $audit->rewrites->groupBy('section_name');

        $html = view('pdf.report', compact('audit', 'sections', 'rewrites'))->render();

        $dir = storage_path('app/pdf');
        @mkdir($dir, 0775, true);
        $htmlPath = "{$dir}/audit-{$audit->id}.html";
        $pdfPath = "{$dir}/audit-{$audit->id}.pdf";
        file_put_contents($htmlPath, $html);

        $process = new Process(
            [NodeBinary::path(), base_path('scripts/pdf.mjs'), '--in', $htmlPath, '--out', $pdfPath],
            base_path(), null, null, 90
        );
        $process->run();

        if (! file_exists($pdfPath)) {
            throw new RuntimeException(sprintf(
                'The PDF could not be produced. exit=%s stdout=%s stderr=%s',
                var_export($process->getExitCode(), true),
                trim($process->getOutput()) ?: '(empty)',
                trim($process->getErrorOutput()) ?: '(empty)',
            ));
        }

        return response()->download($pdfPath, "audit-{$audit->page->name}.pdf")->deleteFileAfterSend(false);
    }

    private function dataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'webp' => 'image/webp', 'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml',
            default => null,
        };

        return $mime
            ? 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path))
            : null;
    }
}
