<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a page and everything that belongs to it.
 *
 * The database cascade takes the audits and their rows — metrics, sections,
 * findings, insights, recommendations, rewrites. What it cannot take is the
 * disk: the screenshots of every section and any PDF that was exported. This
 * class is the only place that knows an audit owns files.
 */
class PageEraser
{
    /**
     * Delete the page, its audits, and their pictures.
     *
     * Rows first, files after, on purpose. If file removal fails you are left
     * with images nobody references, which costs disk and nothing else. The
     * other order leaves live audits pointing at screenshots that are gone,
     * which is a report screen full of broken pictures.
     *
     * The ids are collected before the delete because the cascade happens
     * inside SQLite, where Eloquent's deleting events never fire — an observer
     * on Audit would simply never run.
     */
    public function erase(Page $page): void
    {
        $auditIds = $page->audits()->pluck('id')->all();

        DB::transaction(fn () => $page->delete());

        foreach ($auditIds as $id) {
            $this->forgetFiles($id);
        }
    }

    /** Best effort by design: a missing file is the state we wanted anyway. */
    private function forgetFiles(int $id): void
    {
        Storage::disk('public')->deleteDirectory("screenshots/{$id}");

        foreach (['pdf', 'html'] as $extension) {
            $path = storage_path("app/pdf/audit-{$id}.{$extension}");

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
