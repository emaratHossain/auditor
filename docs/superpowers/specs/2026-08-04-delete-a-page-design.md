# Deleting a page

**Date:** 2026-08-04
**Status:** approved, ready to build

## The problem

The Pages screen accumulates rows nobody wants — pages added by an e2e run, a URL
typed wrong, a campaign that ended. There is no way to remove one. The list only
grows, and the two junk `Rewrite e2e …` rows sitting at the top of it are the
first thing anyone sees.

## What we are building

One **Delete** button per row on the Pages screen. It removes the page and
everything that belongs to it: every audit, every report, every screenshot, every
PDF. A confirm dialog names the page and says how many reports go with it.

Deleting individual audits while keeping the page is **not** in scope. The rows
people want gone are whole pages.

## API

```
DELETE /api/pages/{page}   →  204 No Content
                              404 { message } for an id that is not there
```

`PageController@destroy` stays thin — it calls `App\Services\PageEraser`, which
is the only class that knows an audit owns files on disk.

## Deletion, in order

```php
$auditIds = $page->audits()->pluck('id');

DB::transaction(fn () => $page->delete());   // cascade: audits → metrics,
                                             // sections, findings, insights,
                                             // recommendations, rewrites

foreach ($auditIds as $id) {                 // after commit, best effort
    Storage::disk('public')->deleteDirectory("screenshots/{$id}");
    // storage/app/pdf/audit-{id}.pdf and .html
}
```

**Rows first, files after — deliberately.** If file removal fails you are left
with images nobody references, which is harmless. The other order leaves live
audits pointing at screenshots that no longer exist, which is a broken report
screen.

**The cascade runs inside SQLite**, so Eloquent's `deleting` events never fire
for the audits. Collecting the ids before the delete is what makes file cleanup
possible at all; a model observer would not have worked.

## The queued-audit case

Deleting a page mid-audit leaves up to four chained jobs holding an `auditId`
that no longer resolves. Each job currently calls `Audit::findOrFail`, so it
throws `ModelNotFoundException` and logs a failure. The chain's `catch()` is
already null-safe (`Audit::find($id)?->markFailed(...)`), so nothing worse
happens — but a deliberate deletion should not look like a crash in the log.

Each of the four jobs returns quietly when its audit is gone. One line each.

## Frontend

A **Delete** button beside *Run audit* in each row: muted, red on hover,
`aria-label="Delete page"`. It opens a confirm dialog built on the same modal
shell `MetricsForm` uses (`fixed inset-0 … bg-black/70`, `role="dialog"`):

> **Delete "Rewrite e2e 1785822591285"?**
> Its 3 reports and their screenshots go too. This cannot be undone.
> `[Cancel]` `[Delete page]`

A page that has never been audited says "It has no reports yet." The count comes
from `audits_count`, which `PageController@index` adds with `withCount` and
`PageResource` exposes.

A TanStack mutation invalidates `['pages']` on success. The existing empty state
covers deleting the last page. The Axios interceptor already turns any failure
into a toast, so the component handles no HTTP errors itself.

`ConfirmDialog` is generic, so it lives in `resources/js/features/report/ui.jsx`
beside `Skeleton`, `EmptyState` and `ErrorState` — where `Pages.jsx` already gets
its shared pieces.

## Tests

**`tests/Feature/DeletePageTest.php`**

- deleting returns 204 and the page is gone
- its audits go, and one row from each child table with them
- the screenshot directory and both PDF files are gone from a fake disk
- an unknown id is a 404
- a page that was never audited deletes cleanly
- another page's audits and files are untouched

**`tests/e2e/delete-page.spec.js`**

- add a page, delete it, the row disappears and stays gone after a reload
- cancel leaves the row alone

## Decisions worth recording

- **Page-level only.** Per-audit deletion is a different feature; nobody has
  asked for it.
- **No soft deletes, no undo.** An undo toast needs a restore endpoint and makes
  file cleanup ambiguous — you cannot un-delete a screenshot. A confirm dialog
  that says what is about to be lost is the honest version.
- **Files are removed, not left for a cleanup command.** A `screenshots/`
  directory that grows forever is how local disk fills up quietly.
