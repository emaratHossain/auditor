# DropSense AI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the working V1 landing-page auditor into DropSense AI by adding an HTML crawl, a one-click AI copy rewrite, a real Lighthouse run, a demo analytics fixture that removes typing from the demo, a fifth correlation rule, and a design pass on the report screen.

**Architecture:** The four-stage `Bus::chain()` pipeline is not restructured. `CaptureScreenshotsJob` absorbs two new data sources (the page's own words, and Lighthouse) because it already has a browser open on a loaded page. The rewrite runs *outside* the pipeline, on click, against its own endpoint, so it is never billed on an audit nobody reads. Everything else is additive columns and one new table.

**Tech Stack:** Laravel 12 · PHP 8.3 · SQLite · database queue driver · React 19 + Vite + Tailwind 4 (plain JSX, no TypeScript) · Playwright driven from PHP via `Symfony\Component\Process` · Lighthouse over CDP · PHPUnit · Playwright for e2e.

**Spec:** `docs/superpowers/specs/2026-08-03-dropsense-ai-design.md`

---

## Global Constraints

Every task's requirements implicitly include this section.

- **Never `return $model`.** A Resource or an explicit array is the only place that decides what React sees.
- **Never read a missing number as zero.** A null metric switches off the rules that need it. The report says "not measured".
- **The evidence guarantee:** an insight without a metric, a number and a section name is discarded, not shown.
- **The rename is user-facing only.** `HealthScorer` stays `HealthScorer`; the `audits` table stays `audits`. Do not rename classes, tables, routes or config keys.
- **Product name:** `DropSense AI`. **Score name in the UI:** `Conversion Score`.
- **Lighthouse terminology:** an *audit* is a row in the `audits` table. Lighthouse's individual checks are *checks*, never "audits".
- **No screen shows a blank white area:** loading skeleton, empty state, and error state with a retry, every time.
- **Every metric shown to a user carries a one-line explanation.**
- **No internal vocabulary in the UI.** Users see *landing pages* and *fixes*.
- **Anything from the demo fixture is labelled as demo data**, never passed off as the user's own numbers.
- **All AI replies are schema-validated before anything is saved.** A malformed reply throws; it never half-saves.
- Screenshots go out as temporary signed URLs, never raw disk paths.
- Test commands: `php artisan test` and `npx playwright test`. There is no `npm test` and no `npm run type-check`.
- Migrations are additive only. Do not alter or drop an existing column.

---

## File Structure

**Created:**

| File | Responsibility |
|---|---|
| `config/demo-analytics.php` | The single demo dataset. Read by both the endpoint and the seeder |
| `app/Http/Controllers/DemoMetricsController.php` | Serves the fixture at `GET /api/demo-metrics` |
| `app/Services/Correlation/Rules/RageClickMismatch.php` | The fifth rule |
| `app/Services/Rewrite/RewriteSchema.php` | Validates the model's rewrite reply |
| `app/Services/Rewrite/RewriteResult.php` | Value object: variants + model + tokens |
| `app/Services/Rewrite/CopyRewriter.php` | Interface, mirroring `VisionAnalyzer` |
| `app/Services/Rewrite/StubCopyRewriter.php` | Costs nothing, needs no network, deterministic |
| `app/Services/Rewrite/ClaudeCopyRewriter.php` | The real call |
| `app/Services/Rewrite/RewritePrompt.php` | Builds the instruction from copy + critique + insight |
| `app/Services/Rewrite/RewriteService.php` | Find-or-create a `Rewrite` row for a section + element |
| `app/Models/Rewrite.php` | The new table's model |
| `app/Http/Controllers/RewriteController.php` | `POST /api/audits/{audit}/rewrite` |
| `app/Http/Requests/StoreRewriteRequest.php` | Validates `{section, element}` |
| `resources/js/features/report/RewritePanel.jsx` | The Rewrite this button and its variants |
| `resources/js/features/report/theme.js` | Shared design tokens for the design pass |
| 4 migrations | `copy` on sections, `lighthouse` on audits, Clarity columns on metrics, `rewrites` table |
| 5 test files | See each task |

**Modified:**

| File | Change |
|---|---|
| `scripts/capture.mjs` | Read each section's copy; run Lighthouse; emit both in the same JSON |
| `app/Services/Capture/PlaywrightCaptureDriver.php` | Persist `copy` and `lighthouse` |
| `app/Services/Correlation/Support/Metrics.php` | `+ rageClicks`, `+ deadClicks` and their accessors |
| `app/Services/Correlation/CorrelationService.php` | Register the fifth rule; map the new metrics |
| `app/Services/HealthScorer.php` | Caveats become conditional on measurement |
| `app/Jobs/RankAndScoreJob.php` | Feed Lighthouse into performance and accessibility |
| `app/Http/Requests/StoreAuditRequest.php` | Accept the Clarity fields and `source` |
| `app/Models/PageMetrics.php`, `ScreenshotSection.php`, `Audit.php` | New fillables and casts |
| `app/Http/Resources/AuditReportResource.php` | Carry `copy`, `lighthouse`, `rewrites`, metric source |
| `app/Http/Controllers/AuditController.php` | Eager-load `rewrites` |
| `app/Http/Controllers/ReportPdfController.php` | Pass rewrites to the PDF view |
| `resources/js/app.jsx`, `pages/Pages.jsx`, `pages/Report.jsx`, `features/report/MetricsForm.jsx`, `features/report/ui.jsx` | Rename, pre-fill, rewrite panel, design pass |
| `resources/views/pdf/report.blade.php` | Rename + rewrites section |
| `database/seeders/DemoAuditSeeder.php` | Seed copy, Lighthouse and rewrites |
| `routes/api.php` | Two new routes |
| `app/Providers/AppServiceProvider.php` | Bind `CopyRewriter` |
| `config/ai.php` | `rewrite_driver` key |
| `.env`, `.env.example`, `README.md` | Name |

---

## Task 1: Rename to DropSense AI (`#124`)

**Files:**
- Modify: `.env`, `.env.example`, `README.md`, `resources/js/app.jsx:18-23`, `resources/views/pdf/report.blade.php`, `resources/js/pages/Report.jsx`, `app/Services/HealthScorer.php:26-33`
- Test: `tests/Feature/BrandingTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `HealthScorer::LABELS['overall_label']` is NOT added — the score label lives in the UI only. No PHP interface changes.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BrandingTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandingTest extends TestCase
{
    public function test_the_app_is_called_dropsense_ai(): void
    {
        $this->assertSame('DropSense AI', config('app.name'));
    }

    public function test_the_react_shell_says_dropsense_ai(): void
    {
        $shell = file_get_contents(resource_path('js/app.jsx'));

        $this->assertStringContainsString('DropSense AI', $shell);
        $this->assertStringNotContainsString('Landing Page Auditor', $shell);
    }

    public function test_the_score_is_called_the_conversion_score_in_the_ui(): void
    {
        $report = file_get_contents(resource_path('js/pages/Report.jsx'));
        $pdf    = file_get_contents(resource_path('views/pdf/report.blade.php'));

        $this->assertStringContainsString('Conversion Score', $report);
        $this->assertStringContainsString('Conversion Score', $pdf);
    }

    /** The rename is user-facing only. Renaming a working pipeline mid-week is churn. */
    public function test_the_code_keeps_its_v1_names(): void
    {
        $this->assertTrue(class_exists(\App\Services\HealthScorer::class));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('audits'));
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=BrandingTest`
Expected: FAIL — `Failed asserting that 'Landing Page Auditor' is identical to 'DropSense AI'`

- [ ] **Step 3: Change the app name**

In both `.env` and `.env.example`, replace the `APP_NAME` line with:

```
APP_NAME="DropSense AI"
```

- [ ] **Step 4: Change the React shell**

In `resources/js/app.jsx`, replace the `<header>` block inside `Shell` with:

```jsx
      <header className="mb-8 flex flex-wrap items-baseline justify-between gap-3 border-b border-stone-300 pb-5">
        <Link to="/" className="text-xl font-semibold tracking-tight text-stone-900">
          DropSense AI
        </Link>
        <p className="text-sm text-stone-500">
          Analytics tells you people leave. This tells you why — and rewrites the copy.
        </p>
      </header>
```

- [ ] **Step 5: Rename the score in the report screen**

In `resources/js/pages/Report.jsx`, find every user-visible occurrence of "Health score" / "health score" and replace with "Conversion Score". Run this to find them:

```bash
grep -n -i "health score" resources/js/pages/Report.jsx resources/js/features/report/ui.jsx resources/views/pdf/report.blade.php
```

Replace each. Do **not** touch `HealthScorer.php`, `category_scores`, or any variable name — only text a user reads.

- [ ] **Step 6: Rename in the PDF**

In `resources/views/pdf/report.blade.php`, replace the document title/header text with `DropSense AI` and the score label with `Conversion Score`. Find them with:

```bash
grep -n -i "auditor\|health score" resources/views/pdf/report.blade.php
```

- [ ] **Step 7: Update the README title**

In `README.md`, replace the first heading line with:

```markdown
# DropSense AI
```

and the one-line description under it with:

```markdown
An AI conversion auditor. Paste a landing page URL and it explains *why* visitors leave
and *how* to fix it — with every insight tied to a real number and a real section, and a
one-click rewrite of the copy that is failing.
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --filter=BrandingTest`
Expected: PASS, 4 tests

- [ ] **Step 9: Run the whole suite to prove nothing broke**

Run: `php artisan test`
Expected: PASS — the rename touched no class or column names

- [ ] **Step 10: Commit**

```bash
git add .env.example README.md resources/js/app.jsx resources/js/pages/Report.jsx resources/views/pdf/report.blade.php tests/Feature/BrandingTest.php
git commit -m "Rename the product to DropSense AI, user-facing only

Class names, tables and routes keep their V1 names on purpose: renaming a
working pipeline mid-week is a day of churn with a real chance of breaking
the demo path, and no judge reads a namespace. A test pins both halves —
the new name in the UI, the old names in the code."
```

---

## Task 2: The demo analytics fixture and its endpoint (`#125`)

**Files:**
- Create: `config/demo-analytics.php`, `app/Http/Controllers/DemoMetricsController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/DemoMetricsEndpointTest.php`

**Interfaces:**
- Consumes: nothing
- Produces:
  - `config('demo-analytics')` returns an array with keys `label`, `source`, `visitors` (int), `bounce_rate` (float), `conversion_rate` (float), `cta_click_rate` (float), `mobile_share` (float), `mobile_bounce_rate` (float), `section_reach` (array<string,float>), `rage_clicks` (array<string,int>), `dead_clicks` (array<string,int>)
  - `GET /api/demo-metrics` → `{"data": {...same shape...}}`
  - Task 3 (form pre-fill), Task 11 (the rage-click rule's test data) and Task 13 (the seeder) all read this file.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DemoMetricsEndpointTest.php`:

```php
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
        $this->getJson('/api/demo-metrics')
            ->assertJsonPath('data.visitors', config('demo-analytics.visitors'));
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=DemoMetricsEndpointTest`
Expected: FAIL with 404 — the route does not exist

- [ ] **Step 3: Write the fixture**

Create `config/demo-analytics.php`:

```php
<?php

/*
|--------------------------------------------------------------------------
| The demo dataset
|--------------------------------------------------------------------------
| One realistic set of numbers, shaped like a GA4 export with the two things
| GA4 cannot give you without custom events — per-section scroll depth and a
| real button click rate — plus the two Clarity signals.
|
| ONE file, read by BOTH the endpoint that pre-fills the form and the demo
| seeder. That is deliberate: if they read different sources, what you show on
| stage and what ships in the seed drift apart, and you find out in front of
| the judges.
|
| These are demo numbers and the report says so. The honesty comes from the
| label, not from hiding them — and the form stays editable, so a real number
| can be typed in live if someone asks.
*/

return [
    'label'  => 'Demo analytics — a sample GA4 + Clarity dataset, not your own numbers',
    'source' => 'demo',

    'visitors'        => 18_450,
    'bounce_rate'     => 64.2,
    'conversion_rate' => 1.8,

    // 2.1% against 96% reach is the whole story: they find the button and ignore it.
    'cta_click_rate' => 2.1,

    'mobile_share'       => 68.0,
    'mobile_bounce_rate' => 79.4,

    // How far down visitors actually get. Pricing at 21% is buried.
    'section_reach' => [
        'Hero'         => 96.0,
        'Features'     => 71.0,
        'Testimonials' => 44.0,
        'Pricing'      => 21.0,
        'FAQ'          => 12.0,
    ],

    // Clarity's two signals. Features collects 340 rage clicks because the
    // feature cards have a hover effect and no link behind them.
    'rage_clicks' => [
        'Hero'         => 12,
        'Features'     => 340,
        'Testimonials' => 8,
        'Pricing'      => 41,
        'FAQ'          => 3,
    ],

    'dead_clicks' => [
        'Hero'         => 30,
        'Features'     => 512,
        'Testimonials' => 14,
        'Pricing'      => 60,
        'FAQ'          => 5,
    ],
];
```

- [ ] **Step 4: Write the controller**

Create `app/Http/Controllers/DemoMetricsController.php`:

```php
<?php

namespace App\Http\Controllers;

/**
 * The numbers that pre-fill the metrics form.
 *
 * Reads the same config file the demo seeder reads, so what is shown on stage
 * and what ships in the seed cannot drift apart.
 */
class DemoMetricsController extends Controller
{
    public function __invoke()
    {
        // Not a model, so there is nothing to leak — but the shape still matches
        // every other endpoint the React app talks to.
        return response()->json(['data' => config('demo-analytics')]);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/api.php`, add the import and the route:

```php
use App\Http\Controllers\DemoMetricsController;
```

```php
Route::get('/demo-metrics', DemoMetricsController::class);
```

Put the route above the `/pages` routes — it takes no parameters and belongs with the other flat endpoints.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=DemoMetricsEndpointTest`
Expected: PASS, 4 tests

- [ ] **Step 7: Commit**

```bash
git add config/demo-analytics.php app/Http/Controllers/DemoMetricsController.php routes/api.php tests/Feature/DemoMetricsEndpointTest.php
git commit -m "One demo analytics fixture, served by an endpoint

The endpoint and the seeder read the same file on purpose. If they read
different sources, what you show on stage and what ships in the seed drift
apart and you find out in front of the judges.

A test pins the shape of the story the numbers have to tell, so a later
edit cannot quietly flatten the demo."
```

---

## Task 3: The metrics form arrives already filled in (`#126`)

**Files:**
- Create: `database/migrations/2026_08_03_100001_add_clarity_columns_to_page_metrics_table.php`
- Modify: `app/Models/PageMetrics.php`, `app/Http/Requests/StoreAuditRequest.php`, `app/Http/Resources/AuditReportResource.php:43-50`, `resources/js/features/report/MetricsForm.jsx`
- Test: `tests/Feature/AuditEndpointsTest.php` (add cases)

**Interfaces:**
- Consumes: `GET /api/demo-metrics` from Task 2
- Produces:
  - `page_metrics` gains `rage_clicks` (json, nullable), `dead_clicks` (json, nullable)
  - `PageMetrics::$fillable` gains `rage_clicks`, `dead_clicks`; both cast to `array`
  - `StoreAuditRequest` accepts `rage_clicks` (array of ints), `dead_clicks` (array of ints), `source` (string, in: `demo`,`manual`)
  - The report payload gains `metrics_source` => `{key: 'demo'|'manual', label: string}` at the top level. Task 12 renders it.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/AuditEndpointsTest.php` (keep the file's existing `use` statements; add `App\Models\Audit` if it is not already imported):

```php
    public function test_it_accepts_the_clarity_numbers_and_remembers_they_were_demo_data(): void
    {
        $page = \App\Models\Page::create([
            'name' => 'Pre-fill test',
            'url'  => 'https://example.com/pre-fill',
        ]);

        $response = $this->postJson("/api/pages/{$page->id}/audits", [
            'visitors'        => 18450,
            'bounce_rate'     => 64.2,
            'conversion_rate' => 1.8,
            'rage_clicks'     => ['Features' => 340],
            'dead_clicks'     => ['Features' => 512],
            'source'          => 'demo',
        ]);

        $response->assertCreated();

        $metrics = \App\Models\Audit::find($response->json('data.id'))->metrics;

        $this->assertSame(['Features' => 340], $metrics->rage_clicks);
        $this->assertSame(['Features' => 512], $metrics->dead_clicks);
        $this->assertSame('demo', $metrics->source);
    }

    public function test_the_source_defaults_to_manual_when_nobody_says_otherwise(): void
    {
        $page = \App\Models\Page::create([
            'name' => 'Manual test',
            'url'  => 'https://example.com/manual',
        ]);

        $response = $this->postJson("/api/pages/{$page->id}/audits", [
            'visitors'        => 1000,
            'bounce_rate'     => 50.0,
            'conversion_rate' => 2.0,
        ]);

        $response->assertCreated();

        $this->assertSame('manual', \App\Models\Audit::find($response->json('data.id'))->metrics->source);
    }

    public function test_an_invented_source_is_rejected(): void
    {
        $page = \App\Models\Page::create([
            'name' => 'Bad source',
            'url'  => 'https://example.com/bad-source',
        ]);

        $this->postJson("/api/pages/{$page->id}/audits", [
            'visitors'        => 1000,
            'bounce_rate'     => 50.0,
            'conversion_rate' => 2.0,
            'source'          => 'guessed',
        ])->assertStatus(422)->assertJsonValidationErrors('source');
    }
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=AuditEndpointsTest`
Expected: FAIL — `rage_clicks` is not a column, and `source` is not validated

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_03_100001_add_clarity_columns_to_page_metrics_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_metrics', function (Blueprint $table) {
            // { "Features": 340 } — visitors clicking the same spot repeatedly
            // because nothing happens. Nullable on purpose: a null switches the
            // rage-click rule off entirely rather than reading as a zero.
            $table->json('rage_clicks')->nullable()->after('section_reach');
            $table->json('dead_clicks')->nullable()->after('rage_clicks');
        });
    }

    public function down(): void
    {
        Schema::table('page_metrics', function (Blueprint $table) {
            $table->dropColumn(['rage_clicks', 'dead_clicks']);
        });
    }
};
```

- [ ] **Step 4: Update the model**

In `app/Models/PageMetrics.php`, add the two columns to `$fillable` (after `'section_reach'`):

```php
        'section_reach', 'rage_clicks', 'dead_clicks', 'source',
```

and add the two casts inside `casts()` (after the `section_reach` line):

```php
            'rage_clicks'        => 'array',
            'dead_clicks'        => 'array',
```

- [ ] **Step 5: Update the FormRequest**

In `app/Http/Requests/StoreAuditRequest.php`, add to the returned `rules()` array, after `'section_reach.*'`:

```php
            'rage_clicks'   => ['nullable', 'array'],
            'rage_clicks.*' => ['integer', 'min:0'],
            'dead_clicks'   => ['nullable', 'array'],
            'dead_clicks.*' => ['integer', 'min:0'],

            // Where these numbers came from. The report prints it, so it is not
            // decoration — an unknown value would put an unexplained word on screen.
            'source' => ['nullable', 'string', 'in:demo,manual'],
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=AuditEndpointsTest`
Expected: PASS

- [ ] **Step 7: Add the source to the report payload**

In `app/Http/Resources/AuditReportResource.php`, add this key immediately before the `'metrics' =>` key:

```php
            // The report says where its numbers came from. Demo data is labelled,
            // not hidden — that is what keeps the evidence honest.
            'metrics_source' => [
                'key'   => $metrics->source ?? 'manual',
                'label' => ($metrics?->source ?? 'manual') === 'demo'
                    ? config('demo-analytics.label')
                    : 'Numbers you entered yourself',
            ],
```

- [ ] **Step 8: Pre-fill the form in React**

In `resources/js/features/report/MetricsForm.jsx`, replace the import line and the `useState` block at the top of the component.

Replace line 1:

```jsx
import React, { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import client from '../../api/client'
```

Replace the `const [values, setValues] = useState({})` / `const [reach, setReach] = useState('')` pair with:

```jsx
  const [values, setValues] = useState(null)
  const [reach, setReach] = useState(null)

  // The demo needs zero typing, so the form arrives filled in. The numbers stay
  // visible and overwritable — hiding them would mean the tool could no longer
  // audit a real page, and nobody could answer "whose numbers are these?"
  const demo = useQuery({
    queryKey: ['demo-metrics'],
    queryFn: async () => (await client.get('/demo-metrics')).data.data,
    staleTime: Infinity,
  })

  const d = demo.data
  const filled = values ?? (d ? {
    visitors: d.visitors,
    bounce_rate: d.bounce_rate,
    conversion_rate: d.conversion_rate,
    cta_click_rate: d.cta_click_rate,
    mobile_share: d.mobile_share,
    mobile_bounce_rate: d.mobile_bounce_rate,
  } : {})

  const filledReach = reach ?? (d
    ? Object.entries(d.section_reach).map(([k, v]) => `${k}: ${v}`).join(', ')
    : '')
```

Then replace the `set` helper and the `submit` function with:

```jsx
  const set = (key, v) => setValues({ ...filled, [key]: v })

  const submit = (e) => {
    e.preventDefault()

    // "Hero: 82, Pricing: 20" -> { Hero: 82, Pricing: 20 }
    const section_reach = {}
    filledReach.split(',').forEach((pair) => {
      const [name, value] = pair.split(':').map((s) => s?.trim())
      if (name && value && !Number.isNaN(Number(value))) section_reach[name] = Number(value)
    })

    const payload = { ...filled }
    Object.keys(payload).forEach((k) => { if (payload[k] === '' || payload[k] == null) delete payload[k] })
    if (Object.keys(section_reach).length) payload.section_reach = section_reach

    // Untouched demo values stay demo values. Touch one and it is yours.
    const untouched = values === null && reach === null
    if (untouched && d) {
      payload.source = 'demo'
      payload.rage_clicks = d.rage_clicks
      payload.dead_clicks = d.dead_clicks
    } else {
      payload.source = 'manual'
    }

    onSubmit(payload)
  }
```

In the `Field` component, change `value={values[f.key] ?? ''}` to:

```jsx
          value={filled[f.key] ?? ''}
```

In the section-reach input, change `value={reach}` to `value={filledReach}` and `onChange={(e) => setReach(e.target.value)}` stays as it is.

Finally, replace the paragraph under the `<h3>` with a note that says where the numbers came from:

```jsx
        <p className="mt-1 text-sm text-stone-500">
          {demo.isLoading
            ? 'Loading example numbers…'
            : values === null && reach === null && d
              ? `${d.label}. Press Run audit, or type your own numbers over them.`
              : 'These are your numbers. Only the first three are needed.'}
        </p>
```

- [ ] **Step 9: Verify the whole suite passes**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 10: Check it in a browser**

Run: `php artisan serve` and `npm run dev` in two terminals. Open `http://localhost:8000`, add a page, click **Run audit**.
Expected: every field is already filled in, the note says "Demo analytics — a sample GA4 + Clarity dataset, not your own numbers", and pressing **Run audit** without touching anything starts the audit.

- [ ] **Step 11: Commit**

```bash
git add database/migrations app/Models/PageMetrics.php app/Http/Requests/StoreAuditRequest.php app/Http/Resources/AuditReportResource.php resources/js/features/report/MetricsForm.jsx tests/Feature/AuditEndpointsTest.php
git commit -m "The metrics form arrives already filled in

Zero typing in the demo without deleting the form. Hiding it would have
cost two things worth more than the marginally cleaner flow: the tool
could no longer audit a real page with real numbers, and a judge asking
whose numbers these are would have no answer on screen.

Touch a field and the source flips from demo to manual, so the label on
the report is always true."
```

---

## Task 4: Read the page's own words during capture (`#127`)

**Files:**
- Modify: `scripts/capture.mjs`
- Test: `tests/e2e/capture-copy.spec.js`

**Interfaces:**
- Consumes: nothing
- Produces: each entry in the capture JSON's `sections` array gains a `copy` key:
  ```js
  copy: {
    headline: { text: string, tag: string, selector: string } | null,
    subhead:  { text: string, tag: string, selector: string } | null,
    ctas: Array<{ text: string, tag: string, selector: string }>   // max 3
  }
  ```
  Only desktop entries carry `copy`; the mobile entry carries `copy: null`. Task 5 persists it.

- [ ] **Step 1: Write the failing test**

Create `tests/e2e/capture-copy.spec.js`:

```js
import { test, expect } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'

/**
 * The crawl is driven through the real script against a real local page,
 * because what it has to survive is real markup — not a mock of it.
 */
function capture(html) {
  const dir = mkdtempSync(path.join(tmpdir(), 'dropsense-'))
  const file = path.join(dir, 'page.html')
  writeFileSync(file, html)

  const out = execFileSync('node', [
    'scripts/capture.mjs',
    '--url', `file://${file}`,
    '--out', path.join(dir, 'shots'),
  ], { encoding: 'utf8', timeout: 120000 })

  return JSON.parse(out.trim())
}

const SECTION = (inner) =>
  `<section style="min-height:400px;width:1000px">${inner}</section>`

test('it reads the headline, the subhead and the buttons', () => {
  const result = capture(`<body>
    ${SECTION(`
      <h1>Ship faster than your competitors</h1>
      <p>One dashboard for the whole team.</p>
      <a href="/signup" class="btn">Start free trial</a>
    `)}
    ${SECTION('<h2>Pricing</h2><p>Simple plans.</p><button>Buy now</button>')}
  </body>`)

  expect(result.ok).toBe(true)

  const first = result.sections.find((s) => s.viewport === 'desktop')
  expect(first.copy.headline.text).toBe('Ship faster than your competitors')
  expect(first.copy.subhead.text).toBe('One dashboard for the whole team.')
  expect(first.copy.ctas[0].text).toBe('Start free trial')
  expect(first.copy.ctas[0].selector).toBeTruthy()
})

test('a page with no heading still yields a headline', () => {
  const result = capture(`<body>${SECTION(`
    <div style="font-size:48px">The biggest words on the screen</div>
    <div style="font-size:14px">Much smaller words.</div>
    <a href="/x" class="button">Go</a>
  `)}${SECTION('<h2>Second</h2><p>x</p>')}</body>`)

  const first = result.sections.find((s) => s.viewport === 'desktop')
  expect(first.copy.headline.text).toBe('The biggest words on the screen')
})

test('a footer with many links does not become many rewrite targets', () => {
  const links = Array.from({ length: 30 }, (_, i) => `<a href="/l${i}">Link ${i}</a>`).join('')
  const result = capture(`<body>${SECTION(`<h2>Hero</h2><p>x</p>${links}`)}${SECTION('<h2>Two</h2><p>y</p>')}</body>`)

  const first = result.sections.find((s) => s.viewport === 'desktop')
  expect(first.copy.ctas.length).toBeLessThanOrEqual(3)
})

test('the mobile shot carries no copy of its own', () => {
  const result = capture(`<body>${SECTION('<h1>Hi</h1><p>x</p>')}${SECTION('<h2>Two</h2><p>y</p>')}</body>`)

  expect(result.sections.find((s) => s.viewport === 'mobile').copy).toBeNull()
})
```

- [ ] **Step 2: Run it and watch it fail**

Run: `npx playwright test tests/e2e/capture-copy.spec.js`
Expected: FAIL — `Cannot read properties of undefined (reading 'headline')`

- [ ] **Step 3: Add the copy reader to `capture.mjs`**

In `scripts/capture.mjs`, add these constants next to `MAX_SECTIONS` at the top:

```js
const MAX_CTAS = 3;              // a footer with forty links is not forty rewrite targets
const CTA_MAX_WORDS = 6;         // "Start free trial" is a CTA; a paragraph is not
```

Then add this function immediately after `findSections`:

```js
/**
 * The page's own words, section by section.
 *
 * Screenshots give pixels. You cannot rewrite a headline you never read, so the
 * rewrite feature depends entirely on this — and it happens here because the
 * browser is already open on a loaded page.
 */
async function readCopy(page, sections) {
  return page.evaluate(
    ({ sections, MAX_CTAS, CTA_MAX_WORDS }) => {
      /** A stable-enough selector to find this element again later. */
      const selectorFor = (el) => {
        if (el.id) return `#${CSS.escape(el.id)}`;
        const cls = (el.className || '').toString().trim().split(/\s+/).filter(Boolean)[0];
        const base = cls ? `${el.tagName.toLowerCase()}.${CSS.escape(cls)}` : el.tagName.toLowerCase();
        const siblings = Array.from(el.parentElement?.children ?? []);
        return `${base}:nth-child(${siblings.indexOf(el) + 1})`;
      };

      const entry = (el) => ({
        text: el.innerText.trim().replace(/\s+/g, ' ').slice(0, 300),
        tag: el.tagName.toLowerCase(),
        selector: selectorFor(el),
      });

      /** Everything rendered between two Y offsets. */
      const within = (top, bottom) =>
        Array.from(document.body.querySelectorAll('*')).filter((el) => {
          const r = el.getBoundingClientRect();
          const y = r.top + window.scrollY;
          return y >= top - 1 && y < bottom && r.height > 0 && r.width > 0;
        });

      return sections.map(({ position, height }) => {
        const nodes = within(position, position + height);

        let headline = nodes.find((el) => /^h[1-3]$/.test(el.tagName.toLowerCase()) && el.innerText.trim());

        // Webflow and React marketing pages often have no h1 where you expect
        // one — the biggest words on screen are a styled div. Less precise,
        // never empty.
        if (!headline) {
          const sized = nodes
            .filter((el) => el.children.length === 0 && el.innerText?.trim())
            .map((el) => ({ el, size: parseFloat(getComputedStyle(el).fontSize) || 0 }))
            .sort((a, b) => b.size - a.size);
          headline = sized[0]?.el;
        }

        const after = headline ? nodes.indexOf(headline) : -1;
        const subhead = nodes
          .slice(after + 1)
          .find((el) => el.tagName.toLowerCase() === 'p' && el.innerText.trim());

        const ctas = nodes
          .filter((el) => {
            const tag = el.tagName.toLowerCase();
            if (tag === 'button') return true;
            if (tag !== 'a') return false;
            const text = el.innerText.trim();
            if (!text || text.split(/\s+/).length > CTA_MAX_WORDS) return false;
            // A link that has been styled to look like a button.
            return /btn|button|cta/i.test(el.className || '') ||
              getComputedStyle(el).display !== 'inline';
          })
          .slice(0, MAX_CTAS)
          .map(entry);

        return {
          headline: headline ? entry(headline) : null,
          subhead: subhead ? entry(subhead) : null,
          ctas,
        };
      });
    },
    { sections, MAX_CTAS, CTA_MAX_WORDS },
  );
}
```

- [ ] **Step 4: Call it and attach the result**

In `scripts/capture.mjs`, immediately after the line `const { sections, how } = await findSections(page, selectors);` add:

```js
  const copyPerSection = await readCopy(page, sections);
```

Then in the desktop `captured.push({...})` call, add `copy` as the last key:

```js
    captured.push({
      name: s.name, viewport: 'desktop', file,
      position: s.position, height, page_height: pageHeight, sort_order: i,
      copy: copyPerSection[i] ?? null,
    });
```

And in the mobile `captured.push({...})` call, add:

```js
    copy: null,
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `npx playwright test tests/e2e/capture-copy.spec.js`
Expected: PASS, 4 tests

- [ ] **Step 6: Run it against a real page and read the output**

```bash
node scripts/capture.mjs --url https://stripe.com --out /tmp/dropsense-real | python3 -m json.tool | grep -A6 '"copy"' | head -40
```

Expected: real headline text, not `null` and not navigation-menu labels. If the headline is a nav item, the section detection put the section boundary in the wrong place — that is a section-detection problem, not a crawl problem, and the optional CSS-selector field on the page record is the escape hatch.

- [ ] **Step 7: Commit**

```bash
git add scripts/capture.mjs tests/e2e/capture-copy.spec.js
git commit -m "Read the page's own words during capture

Screenshots give pixels; you cannot rewrite a headline you never read.
This happens inside the existing capture visit because the browser is
already open on a loaded page — a separate crawl stage would launch a
second browser to re-fetch it.

Capped at one headline, one subhead and three buttons per section so a
footer with forty links does not become forty rewrite targets. When there
is no heading, the largest rendered text wins: less precise, never empty."
```

---

## Task 5: Store the copy and show it in the report (`#128`)

**Files:**
- Create: `database/migrations/2026_08_03_100002_add_copy_to_screenshot_sections_table.php`
- Modify: `app/Models/ScreenshotSection.php`, `app/Services/Capture/PlaywrightCaptureDriver.php:48-57`, `app/Services/Capture/StubCaptureDriver.php`, `app/Http/Resources/AuditReportResource.php:52-66`
- Test: `tests/Feature/AuditEndpointsTest.php` (add a case)

**Interfaces:**
- Consumes: the `copy` key produced by Task 4
- Produces:
  - `screenshot_sections.copy` (json, nullable), cast to `array` on the model
  - Each entry in the report's `sections` array gains `copy` with the same shape Task 4 emits, or `null`
  - Task 6 reads `ScreenshotSection::$copy`; Task 8 reads `section.copy` in React

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/AuditEndpointsTest.php`:

```php
    public function test_the_report_carries_the_pages_own_words(): void
    {
        $page = \App\Models\Page::create([
            'name' => 'Copy test',
            'url'  => 'https://example.com/copy',
        ]);

        $audit = $page->audits()->create([
            'status' => \App\Models\Audit::STATUS_COMPLETED,
        ]);

        $audit->sections()->create([
            'section_name'    => 'Hero',
            'viewport'        => 'desktop',
            'screenshot_path' => 'screenshots/1/hero-0-desktop.webp',
            'position'        => 0,
            'height'          => 900,
            'page_height'     => 4500,
            'sort_order'      => 0,
            'copy'            => [
                'headline' => ['text' => 'Ship faster', 'tag' => 'h1', 'selector' => 'h1'],
                'subhead'  => ['text' => 'One dashboard.', 'tag' => 'p', 'selector' => 'p:nth-child(2)'],
                'ctas'     => [['text' => 'Start free trial', 'tag' => 'a', 'selector' => 'a.btn']],
            ],
        ]);

        $this->getJson("/api/audits/{$audit->id}/report")
            ->assertOk()
            ->assertJsonPath('data.sections.0.copy.headline.text', 'Ship faster')
            ->assertJsonPath('data.sections.0.copy.ctas.0.text', 'Start free trial');
    }

    public function test_a_section_with_no_readable_copy_returns_null_not_an_empty_shell(): void
    {
        $page = \App\Models\Page::create([
            'name' => 'No copy',
            'url'  => 'https://example.com/no-copy',
        ]);

        $audit = $page->audits()->create(['status' => \App\Models\Audit::STATUS_COMPLETED]);

        $audit->sections()->create([
            'section_name'    => 'Hero',
            'viewport'        => 'desktop',
            'screenshot_path' => 'screenshots/2/hero-0-desktop.webp',
            'position'        => 0,
            'height'          => 900,
            'page_height'     => 4500,
            'sort_order'      => 0,
        ]);

        $this->getJson("/api/audits/{$audit->id}/report")
            ->assertOk()
            ->assertJsonPath('data.sections.0.copy', null);
    }
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=AuditEndpointsTest`
Expected: FAIL — `copy` is not a column

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_03_100002_add_copy_to_screenshot_sections_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screenshot_sections', function (Blueprint $table) {
            // { headline: {...}, subhead: {...}, ctas: [...] } — the section's own
            // words, read during capture. An attribute of a section we already
            // record, so it needs no table of its own.
            $table->json('copy')->nullable()->after('screenshot_path');
        });
    }

    public function down(): void
    {
        Schema::table('screenshot_sections', function (Blueprint $table) {
            $table->dropColumn('copy');
        });
    }
};
```

- [ ] **Step 4: Update the model**

In `app/Models/ScreenshotSection.php`, add `'copy'` to `$fillable`:

```php
    protected $fillable = [
        'audit_id', 'section_name', 'viewport', 'screenshot_path', 'copy',
        'position', 'height', 'page_height', 'sort_order',
    ];
```

and add the cast:

```php
            'copy'        => 'array',
```

- [ ] **Step 5: Persist it in the driver**

In `app/Services/Capture/PlaywrightCaptureDriver.php`, add `copy` to the `create()` array:

```php
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
```

- [ ] **Step 6: Give the stub driver copy too**

The stub driver is what the demo falls back to and what most tests run against, so it must produce the same shape. In `app/Services/Capture/StubCaptureDriver.php`, find the `sections()->create([...])` call and add:

```php
                'copy' => [
                    'headline' => ['text' => "The {$name} headline goes here", 'tag' => 'h2', 'selector' => 'h2'],
                    'subhead'  => ['text' => 'A supporting line under it.', 'tag' => 'p', 'selector' => 'p'],
                    'ctas'     => [['text' => 'Get started', 'tag' => 'a', 'selector' => 'a.btn']],
                ],
```

Use whatever variable the stub already holds the section name in — read the file first and match it. If the stub builds sections from a fixed array, add the `copy` key to each entry in that array instead.

- [ ] **Step 7: Expose it in the report**

In `app/Http/Resources/AuditReportResource.php`, inside the `sections` map, add `copy` after `above_the_fold`:

```php
                    'copy'             => $section->copy,
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=AuditEndpointsTest`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add database/migrations app/Models/ScreenshotSection.php app/Services/Capture app/Http/Resources/AuditReportResource.php tests/Feature/AuditEndpointsTest.php
git commit -m "Store the page's own words and return them in the report

A json column on screenshot_sections rather than a table of its own: the
page's words are an attribute of a section we already record.

The stub capture driver produces the same shape as the real one, because
it is what the demo falls back to and what the test suite runs against."
```

---

## Task 6: The rewrite service, and a reply it refuses to half-save (`#130`, `#136`)

**Files:**
- Create: `database/migrations/2026_08_03_100003_create_rewrites_table.php`, `app/Models/Rewrite.php`, `app/Services/Rewrite/RewriteSchema.php`, `app/Services/Rewrite/RewriteResult.php`, `app/Services/Rewrite/CopyRewriter.php`, `app/Services/Rewrite/StubCopyRewriter.php`, `app/Services/Rewrite/ClaudeCopyRewriter.php`, `app/Services/Rewrite/RewritePrompt.php`, `app/Services/Rewrite/RewriteService.php`
- Modify: `app/Models/Audit.php`, `app/Providers/AppServiceProvider.php`, `config/ai.php`, `.env.example`
- Test: `tests/Unit/Rewrite/RewriteSchemaTest.php`, `tests/Unit/Rewrite/RewriteServiceTest.php`

**Interfaces:**
- Consumes: `ScreenshotSection::$copy` (Task 5), `AiFinding::$problems`, `Insight::$statement` and `$evidence`
- Produces:
  - `rewrites` table: `audit_id`, `section_name`, `element`, `original`, `variants` (json), `model`, `tokens`
  - `Audit::rewrites(): HasMany`
  - `RewriteResult` — `readonly class` with `array $variants`, `string $model`, `int $tokens`
  - `CopyRewriter::rewrite(Audit $audit, string $sectionName, string $element, string $original, string $critique, ?string $insight): RewriteResult`
  - `RewriteService::forElement(Audit $audit, string $sectionName, string $element): Rewrite` — returns the stored row if one exists, otherwise calls the driver, validates, and creates one. Throws `InvalidArgumentException` if the section or element is unknown.
  - `$element` is one of `headline`, `subhead`, `cta`. Task 7 validates against exactly this list.
  - Each variant is `['text' => string, 'reason' => string]`.

- [ ] **Step 1: Write the failing schema test**

Create `tests/Unit/Rewrite/RewriteSchemaTest.php`:

```php
<?php

namespace Tests\Unit\Rewrite;

use App\Services\Rewrite\RewriteSchema;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * A model will eventually return something off-shape. When it does, the row must
 * not be written — a half-saved rewrite that looks finished is worse than an
 * error saying the call failed.
 */
class RewriteSchemaTest extends TestCase
{
    private function schema(): RewriteSchema
    {
        return new RewriteSchema;
    }

    public function test_it_accepts_a_well_formed_reply(): void
    {
        $reply = ['variants' => [
            ['text' => 'Ship in a week, not a quarter', 'reason' => 'Names the outcome and the timescale.'],
            ['text' => 'Cut your release cycle in half', 'reason' => 'Quantifies the promise.'],
        ]];

        $this->assertSame($reply['variants'], $this->schema()->validate($reply)['variants']);
    }

    public function test_a_reply_that_is_not_json_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('was not JSON');

        $this->schema()->validate('sorry, I cannot help with that');
    }

    public function test_a_reply_with_no_variants_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carried no versions');

        $this->schema()->validate(['variants' => []]);
    }

    public function test_a_variant_missing_its_reason_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("missing 'reason'");

        $this->schema()->validate(['variants' => [['text' => 'Some new headline']]]);
    }

    public function test_a_variant_with_empty_text_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');

        $this->schema()->validate(['variants' => [['text' => '   ', 'reason' => 'because']]]);
    }

    public function test_more_than_three_versions_is_trimmed_not_rejected(): void
    {
        $reply = ['variants' => array_map(
            fn ($i) => ['text' => "Version {$i}", 'reason' => "Reason {$i}"],
            range(1, 6),
        )];

        // Six is not malformed, it is just more than anyone reads on stage.
        $this->assertCount(3, $this->schema()->validate($reply)['variants']);
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=RewriteSchemaTest`
Expected: FAIL — `Class "App\Services\Rewrite\RewriteSchema" not found`

- [ ] **Step 3: Write the schema**

Create `app/Services/Rewrite/RewriteSchema.php`:

```php
<?php

namespace App\Services\Rewrite;

use InvalidArgumentException;

/**
 * The agreed shape of a rewrite reply.
 *
 * Held to the same bar as AuditSchema: a malformed reply throws rather than
 * writing a half-parsed row, because a rewrite that looks finished and is not
 * is worse than an honest failure.
 */
class RewriteSchema
{
    /** More than this is more than anyone reads on stage. */
    public const MAX_VARIANTS = 3;

    /** The JSON shape asked of the model. Shared by every driver. */
    public static function forPrompt(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['variants'],
            'properties' => [
                'variants' => [
                    'type'     => 'array',
                    'minItems' => 2,
                    'maxItems' => self::MAX_VARIANTS,
                    'items'    => [
                        'type'       => 'object',
                        'required'   => ['text', 'reason'],
                        'properties' => [
                            'text'   => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{variants:array<int,array{text:string,reason:string}>}
     *
     * @throws InvalidArgumentException with a message a human can act on
     */
    public function validate(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('The rewrite reply was not JSON at all.');
        }

        $variants = $decoded['variants'] ?? null;

        if (! is_array($variants) || $variants === []) {
            throw new InvalidArgumentException('The rewrite reply carried no versions.');
        }

        foreach ($variants as $i => $variant) {
            $where = 'version #'.($i + 1);

            if (! is_array($variant)) {
                throw new InvalidArgumentException("The rewrite reply has a malformed {$where}.");
            }

            foreach (['text', 'reason'] as $field) {
                if (! array_key_exists($field, $variant)) {
                    throw new InvalidArgumentException("The rewrite reply is missing '{$field}' on {$where}.");
                }

                if (! is_string($variant[$field]) || trim($variant[$field]) === '') {
                    throw new InvalidArgumentException("The rewrite reply has an empty '{$field}' on {$where}.");
                }
            }
        }

        // Trimming is not rejecting. Six versions is not malformed, it is just noise.
        $decoded['variants'] = array_slice(array_values($variants), 0, self::MAX_VARIANTS);

        return $decoded;
    }
}
```

- [ ] **Step 4: Run the schema test to verify it passes**

Run: `php artisan test --filter=RewriteSchemaTest`
Expected: PASS, 6 tests

- [ ] **Step 5: Commit the schema**

```bash
git add app/Services/Rewrite/RewriteSchema.php tests/Unit/Rewrite/RewriteSchemaTest.php
git commit -m "Validate a rewrite reply before anything is saved

Same bar as AuditSchema. Six versions is trimmed rather than rejected:
that is noise, not malformation."
```

- [ ] **Step 6: Write the failing service test**

Create `tests/Unit/Rewrite/RewriteServiceTest.php`:

```php
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
}
```

- [ ] **Step 7: Run it and watch it fail**

Run: `php artisan test --filter=RewriteServiceTest`
Expected: FAIL — `Class "App\Services\Rewrite\RewriteService" not found`

- [ ] **Step 8: Write the migration and model**

Create `database/migrations/2026_08_03_100003_create_rewrites_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewrites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();

            $table->string('section_name');

            // headline | subhead | cta
            $table->string('element');

            $table->text('original');

            // [{ text, reason }, ...] — at most three
            $table->json('variants');

            $table->string('model')->nullable();
            $table->unsignedInteger('tokens')->default(0);

            $table->timestamps();

            // One stored rewrite per element, so the second click is free and the
            // seeded demo page can ship with its rewrites already in place.
            $table->unique(['audit_id', 'section_name', 'element']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewrites');
    }
};
```

Create `app/Models/Rewrite.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Two or three better versions of one piece of copy, with a reason for each.
 *
 * Stored rather than recomputed, so the second click is free, the PDF can carry
 * them, and the seeded demo page survives a dead network on stage.
 */
class Rewrite extends Model
{
    use HasFactory;

    public const ELEMENTS = ['headline', 'subhead', 'cta'];

    protected $fillable = [
        'audit_id', 'section_name', 'element', 'original', 'variants', 'model', 'tokens',
    ];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'tokens'   => 'integer',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
```

In `app/Models/Audit.php`, add the relation next to the others:

```php
    public function rewrites(): HasMany         { return $this->hasMany(Rewrite::class); }
```

- [ ] **Step 9: Write the result object and the driver interface**

Create `app/Services/Rewrite/RewriteResult.php`:

```php
<?php

namespace App\Services\Rewrite;

readonly class RewriteResult
{
    public function __construct(
        /** @var array<int,array{text:string,reason:string}> */
        public array $variants,
        public string $model,
        public int $tokens = 0,
    ) {}
}
```

Create `app/Services/Rewrite/CopyRewriter.php`:

```php
<?php

namespace App\Services\Rewrite;

use App\Models\Audit;

/**
 * One text-only call, made on click rather than during the audit.
 *
 * In the pipeline it would be billed on every audit whether or not anyone reads
 * it — and it would turn the demo's live moment into a reveal of something
 * prepared earlier.
 */
interface CopyRewriter
{
    /**
     * @param  string  $critique  what the vision pass said about this section
     * @param  string|null  $insight  the correlation insight, if one attached to this section
     */
    public function rewrite(
        Audit $audit,
        string $sectionName,
        string $element,
        string $original,
        string $critique,
        ?string $insight,
    ): RewriteResult;

    public function modelName(): string;
}
```

- [ ] **Step 10: Write the stub driver**

Create `app/Services/Rewrite/StubCopyRewriter.php`:

```php
<?php

namespace App\Services\Rewrite;

use App\Models\Audit;

/**
 * Costs nothing, needs no network, and is deliberately NOT random — the same
 * copy always produces the same versions, so a rehearsal matches the demo.
 *
 * This is also the build-week default and the safety net if the venue network
 * dies: set AI_REWRITE_DRIVER=stub and the button still works.
 */
class StubCopyRewriter implements CopyRewriter
{
    public function modelName(): string
    {
        return 'stub';
    }

    public function rewrite(
        Audit $audit,
        string $sectionName,
        string $element,
        string $original,
        string $critique,
        ?string $insight,
    ): RewriteResult {
        $evidence = $insight ? ' It answers the problem the numbers show.' : '';

        return new RewriteResult(
            variants: [
                [
                    'text'   => $this->outcomeLed($original, $element),
                    'reason' => 'Leads with the outcome instead of describing the product.'.$evidence,
                ],
                [
                    'text'   => $this->specific($original, $element),
                    'reason' => 'Replaces a general claim with something a reader can picture.',
                ],
                [
                    'text'   => $this->direct($original, $element),
                    'reason' => 'Shorter and more direct, which suits a first-time visitor who is scanning.',
                ],
            ],
            model: $this->modelName(),
            tokens: 0,
        );
    }

    private function outcomeLed(string $original, string $element): string
    {
        return $element === 'cta'
            ? 'Start free — no card needed'
            : 'Get '.lcfirst(rtrim($original, '.')).' working by Friday';
    }

    private function specific(string $original, string $element): string
    {
        return $element === 'cta'
            ? 'See it on your own page'
            : rtrim($original, '.').' — in about ten minutes';
    }

    private function direct(string $original, string $element): string
    {
        return $element === 'cta'
            ? 'Try it free'
            : ucfirst(strtok(rtrim($original, '.'), ',') ?: $original);
    }
}
```

- [ ] **Step 11: Write the service**

Create `app/Services/Rewrite/RewriteService.php`:

```php
<?php

namespace App\Services\Rewrite;

use App\Models\Audit;
use App\Models\Rewrite;
use App\Models\ScreenshotSection;
use InvalidArgumentException;

/**
 * Find-or-create one rewrite per section and element.
 *
 * The service is what makes this more than a thesaurus: it hands the model the
 * original words, the critique of that section, AND the correlation insight —
 * so the rewrite is told why the copy is failing, in numbers.
 */
class RewriteService
{
    public function __construct(
        private CopyRewriter $rewriter,
        private RewriteSchema $schema = new RewriteSchema,
        private RewritePrompt $prompt = new RewritePrompt,
    ) {}

    public function forElement(Audit $audit, string $sectionName, string $element): Rewrite
    {
        if (! in_array($element, Rewrite::ELEMENTS, true)) {
            throw new InvalidArgumentException(
                "There is nothing called '{$element}' to rewrite. Expected one of: ".implode(', ', Rewrite::ELEMENTS).'.'
            );
        }

        $section = $this->section($audit, $sectionName);
        $original = $this->originalText($section, $element);

        // The second click is free. A rehearsal must not cost as much as the demo.
        $stored = $audit->rewrites()
            ->where('section_name', $section->section_name)
            ->where('element', $element)
            ->first();

        if ($stored) {
            return $stored;
        }

        $result = $this->rewriter->rewrite(
            audit: $audit,
            sectionName: $section->section_name,
            element: $element,
            original: $original,
            critique: $this->prompt->critiqueFor($audit, $section->section_name),
            insight: $this->prompt->insightFor($audit, $section->section_name),
        );

        // Validate before anything is written. A half-saved rewrite that looks
        // finished is worse than an error saying the call failed.
        $validated = $this->schema->validate(['variants' => $result->variants]);

        return $audit->rewrites()->create([
            'section_name' => $section->section_name,
            'element'      => $element,
            'original'     => $original,
            'variants'     => $validated['variants'],
            'model'        => $result->model,
            'tokens'       => $result->tokens,
        ]);
    }

    private function section(Audit $audit, string $sectionName): ScreenshotSection
    {
        $section = $audit->sections()
            ->where('viewport', 'desktop')
            ->get()
            ->first(fn (ScreenshotSection $s) => strcasecmp($s->section_name, $sectionName) === 0);

        if (! $section) {
            throw new InvalidArgumentException("This audit has no section called '{$sectionName}'.");
        }

        return $section;
    }

    private function originalText(ScreenshotSection $section, string $element): string
    {
        $copy = $section->copy ?? [];

        $text = $element === 'cta'
            ? ($copy['ctas'][0]['text'] ?? null)
            : ($copy[$element]['text'] ?? null);

        if (! is_string($text) || trim($text) === '') {
            throw new InvalidArgumentException(
                "We could not read a {$element} on {$section->section_name}, so there is no {$element} to rewrite."
            );
        }

        return $text;
    }
}
```

- [ ] **Step 12: Write the prompt builder**

Create `app/Services/Rewrite/RewritePrompt.php`:

```php
<?php

namespace App\Services\Rewrite;

use App\Models\Audit;

/**
 * The three things that stop this being a thesaurus.
 *
 * A rewrite tool handed only the old words can guess at style. This one is
 * handed the critique of the section and the number that proves the copy is
 * failing, and its stated reason has to refer to them.
 */
class RewritePrompt
{
    /** What the vision pass said about this section, as plain sentences. */
    public function critiqueFor(Audit $audit, string $sectionName): string
    {
        $finding = $audit->findings
            ->first(fn ($f) => strcasecmp($f->section_name, $sectionName) === 0);

        $problems = collect($finding?->problems ?? [])
            ->map(fn ($p) => '- '.($p['what'] ?? '').' '.($p['why'] ?? ''))
            ->implode("\n");

        return trim($problems) ?: 'No specific problem was flagged on this section.';
    }

    /** The correlation insight for this section, if one survived the evidence guarantee. */
    public function insightFor(Audit $audit, string $sectionName): ?string
    {
        $insight = $audit->insights
            ->first(fn ($i) => strcasecmp($i->section_name, $sectionName) === 0);

        return $insight?->statement;
    }

    public function instruction(
        string $url,
        string $sectionName,
        string $element,
        string $original,
        string $critique,
        ?string $insight,
    ): string {
        $name = match ($element) {
            'headline' => 'headline',
            'subhead'  => 'supporting line',
            'cta'      => 'call-to-action button label',
        };

        $evidence = $insight
            ? "What this page's real numbers show about this section:\n{$insight}"
            : 'There is no measured insight for this section, so judge it on the critique alone.';

        $length = $element === 'cta'
            ? 'Keep every version to four words or fewer — it has to fit on a button.'
            : 'Keep every version under fifteen words.';

        return <<<TXT
        You are an experienced conversion copywriter improving one piece of copy on the
        landing page at {$url}.

        The section: {$sectionName}
        The {$name}, exactly as it appears on the page: "{$original}"

        What a UX reviewer said about this section:
        {$critique}

        {$evidence}

        Write two or three replacements. For each one, give a single-sentence reason that
        refers to the critique or the number above — not a general principle about good
        copywriting. If a number is available, name it in at least one reason.

        {$length}
        Write in the voice the page already uses. Do not invent product features,
        statistics, prices or guarantees that are not already in the original.
        TXT;
    }
}
```

- [ ] **Step 13: Run the service test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=RewriteServiceTest`
Expected: PASS, 5 tests

- [ ] **Step 14: Write the Claude driver**

Create `app/Services/Rewrite/ClaudeCopyRewriter.php`:

```php
<?php

namespace App\Services\Rewrite;

use App\Models\Audit;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * One text-only call. Roughly a tenth of a cent, so a click costs nothing worth
 * thinking about — which is exactly why it can be on-demand.
 */
class ClaudeCopyRewriter implements CopyRewriter
{
    public function __construct(private RewritePrompt $prompt = new RewritePrompt) {}

    public function modelName(): string
    {
        return (string) config('ai.claude.model');
    }

    public function rewrite(
        Audit $audit,
        string $sectionName,
        string $element,
        string $original,
        string $critique,
        ?string $insight,
    ): RewriteResult {
        $key = config('ai.claude.key');

        if (blank($key)) {
            throw new RuntimeException('No Anthropic API key is set, so the copy could not be rewritten. Add ANTHROPIC_API_KEY to .env, or set AI_REWRITE_DRIVER=stub.');
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->modelName(),
                'max_tokens' => 1200,
                'messages'   => [['role' => 'user', 'content' => $this->prompt->instruction(
                    url: $audit->page->url,
                    sectionName: $sectionName,
                    element: $element,
                    original: $original,
                    critique: $critique,
                    insight: $insight,
                )]],
                'output_config' => [
                    'format' => ['type' => 'json_schema', 'schema' => RewriteSchema::forPrompt()],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('The rewrite request was refused ('.$response->status().'). '.$response->json('error.message', ''));
        }

        if ($response->json('stop_reason') === 'refusal') {
            throw new RuntimeException('The model declined to rewrite this copy.');
        }

        $text = collect($response->json('content', []))->firstWhere('type', 'text')['text'] ?? null;
        $decoded = json_decode((string) $text, true);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('The rewrite reply was not JSON at all.');
        }

        return new RewriteResult(
            variants: $decoded['variants'] ?? [],
            model: $this->modelName(),
            tokens: (int) $response->json('usage.input_tokens', 0) + (int) $response->json('usage.output_tokens', 0),
        );
    }
}
```

- [ ] **Step 15: Bind the driver**

In `config/ai.php`, add after the `driver` key:

```php
    // stub | claude — the rewrite is a separate switch from the vision call, so
    // you can run the expensive vision pass on stub while testing real rewrites.
    'rewrite_driver' => env('AI_REWRITE_DRIVER', env('AI_DRIVER', 'stub')),
```

In `app/Providers/AppServiceProvider.php`, add the imports:

```php
use App\Services\Rewrite\ClaudeCopyRewriter;
use App\Services\Rewrite\CopyRewriter;
use App\Services\Rewrite\StubCopyRewriter;
```

and the binding inside `register()`:

```php
        $this->app->bind(CopyRewriter::class, fn () => match (config('ai.rewrite_driver')) {
            'claude' => new ClaudeCopyRewriter,
            default  => new StubCopyRewriter,
        });
```

In `.env.example`, add under the existing `AI_DRIVER` line:

```
AI_REWRITE_DRIVER=stub
```

- [ ] **Step 16: Run the whole suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 17: Commit**

```bash
git add database/migrations app/Models/Rewrite.php app/Models/Audit.php app/Services/Rewrite app/Providers/AppServiceProvider.php config/ai.php .env.example tests/Unit/Rewrite
git commit -m "The rewrite service: three inputs, validated before saving

The third input is the product. A rewrite tool handed only the old words
can guess at style; this one is handed the critique of the section and the
number that proves the copy is failing, and the reason it returns has to
refer to them.

Stored per section and element with a unique index, so the second click is
free, the PDF can carry them, and the seeded demo page can ship with its
rewrites already in place."
```

---

## Task 7: The endpoint the Rewrite button calls (`#131`, `#138`)

**Files:**
- Create: `app/Http/Controllers/RewriteController.php`, `app/Http/Requests/StoreRewriteRequest.php`, `tests/Feature/RewriteEndpointTest.php`
- Modify: `routes/api.php`, `app/Http/Controllers/AuditController.php:33`, `app/Http/Resources/AuditReportResource.php`

**Interfaces:**
- Consumes: `RewriteService::forElement()` from Task 6
- Produces:
  - `POST /api/audits/{audit}/rewrite` with `{section: string, element: 'headline'|'subhead'|'cta'}` → `201 {"data": {id, section, element, original, variants, model}}`
  - The report payload gains a top-level `rewrites` array of the same object shape
  - Task 8 calls this endpoint and reads `report.rewrites`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RewriteEndpointTest.php`:

```php
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
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=RewriteEndpointTest`
Expected: FAIL with 404 — the route does not exist

- [ ] **Step 3: Write the FormRequest**

Create `app/Http/Requests/StoreRewriteRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Rewrite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRewriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'max:255'],
            'element' => ['required', 'string', Rule::in(Rewrite::ELEMENTS)],
        ];
    }

    public function messages(): array
    {
        return [
            'element.in' => 'We can rewrite a headline, a supporting line or a button label.',
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

Create `app/Http/Controllers/RewriteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRewriteRequest;
use App\Models\Audit;
use App\Services\Rewrite\RewriteService;
use InvalidArgumentException;

class RewriteController extends Controller
{
    public function __construct(private RewriteService $rewrites) {}

    public function __invoke(StoreRewriteRequest $request, Audit $audit)
    {
        $audit->load(['page', 'findings', 'insights']);

        try {
            $rewrite = $this->rewrites->forElement(
                $audit,
                $request->string('section')->toString(),
                $request->string('element')->toString(),
            );
        } catch (InvalidArgumentException $e) {
            // The section or element does not exist on this page. That is a bad
            // request, not a server fault, and the message is already written
            // for a human to read.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Never return the model. A Resource-shaped array is the only place that
        // decides what React sees.
        return response()->json(['data' => [
            'id'       => $rewrite->id,
            'section'  => $rewrite->section_name,
            'element'  => $rewrite->element,
            'original' => $rewrite->original,
            'variants' => $rewrite->variants,
            'model'    => $rewrite->model,
        ]], 201);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/api.php`, add the import:

```php
use App\Http\Controllers\RewriteController;
```

and the route next to the other `/audits/{audit}` routes:

```php
Route::post('/audits/{audit}/rewrite', RewriteController::class);
```

- [ ] **Step 6: Carry stored rewrites in the report**

In `app/Http/Controllers/AuditController.php`, add `'rewrites'` to the `report()` eager load:

```php
        $audit->load(['page', 'metrics', 'sections', 'findings', 'insights', 'recommendations', 'rewrites']);
```

In `app/Http/Resources/AuditReportResource.php`, add this key after `'insights'`:

```php
            // Rides along in the one response rather than needing a call of its
            // own — and it is what the panel falls back to when a live call fails.
            'rewrites' => $this->rewrites->map(fn ($r) => [
                'id'       => $r->id,
                'section'  => $r->section_name,
                'element'  => $r->element,
                'original' => $r->original,
                'variants' => $r->variants,
                'model'    => $r->model,
            ]),
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=RewriteEndpointTest`
Expected: PASS, 5 tests

- [ ] **Step 8: Run the whole suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers app/Http/Requests/StoreRewriteRequest.php app/Http/Resources/AuditReportResource.php routes/api.php tests/Feature/RewriteEndpointTest.php
git commit -m "One endpoint the Rewrite button calls

Stored rewrites ride along in the single report response rather than
needing a call of their own — which is also what the panel falls back to
when a live call fails.

An unknown section is a 422 with a sentence a human can read, not a stack
trace: the service already writes those messages for a person."
```

---

## Task 8: The Rewrite panel, and what happens when the wifi dies (`#132`)

**Files:**
- Create: `resources/js/features/report/RewritePanel.jsx`
- Modify: `resources/js/pages/Report.jsx`, `resources/views/pdf/report.blade.php`, `app/Http/Controllers/ReportPdfController.php`
- Test: `tests/e2e/rewrite.spec.js`

**Interfaces:**
- Consumes: `POST /api/audits/{id}/rewrite` and `report.rewrites` from Task 7, `section.copy` from Task 5
- Produces: no new API surface. `<RewritePanel auditId section stored />` where `stored` is the array of rewrites already in the report payload for that section.

- [ ] **Step 1: Write the failing test**

Create `tests/e2e/rewrite.spec.js`:

```js
import { test, expect } from '@playwright/test'

/**
 * Runs against the seeded demo audit, because that is what the demo runs
 * against. Seed first:  php artisan db:seed --class=DemoAuditSeeder
 */
test.describe('the rewrite panel', () => {
  test('it shows the real headline and rewrites it on click', async ({ page }) => {
    await page.goto('/')
    await page.getByRole('link', { name: /pricing/i }).first().click()

    const hero = page.getByTestId('section-card').first()

    // The page's own words, as text — not only as a picture.
    await expect(hero.getByTestId('copy-headline')).not.toBeEmpty()

    await hero.getByRole('button', { name: /rewrite this/i }).first().click()

    const variants = hero.getByTestId('rewrite-variant')
    await expect(variants.first()).toBeVisible({ timeout: 20000 })
    await expect(await variants.count()).toBeGreaterThanOrEqual(2)

    // Every version carries a reason. A rewrite with no reason is a thesaurus.
    await expect(hero.getByTestId('rewrite-reason').first()).not.toBeEmpty()
  })

  test('a failed live call falls back to the stored versions and says so', async ({ page }) => {
    await page.route('**/api/audits/*/rewrite', (route) => route.abort('failed'))

    await page.goto('/')
    await page.getByRole('link', { name: /pricing/i }).first().click()

    const hero = page.getByTestId('section-card').first()
    await hero.getByRole('button', { name: /rewrite this/i }).first().click()

    await expect(hero.getByTestId('rewrite-variant').first()).toBeVisible()
    await expect(hero.getByText(/could not reach|saved earlier/i)).toBeVisible()
  })

  test('there is never a blank area while it thinks', async ({ page }) => {
    await page.route('**/api/audits/*/rewrite', async (route) => {
      await new Promise((r) => setTimeout(r, 1500))
      route.continue()
    })

    await page.goto('/')
    await page.getByRole('link', { name: /pricing/i }).first().click()

    const hero = page.getByTestId('section-card').first()
    await hero.getByRole('button', { name: /rewrite this/i }).first().click()

    await expect(hero.getByTestId('rewrite-loading')).toBeVisible()
  })
})
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan db:seed --class=DemoAuditSeeder && npx playwright test tests/e2e/rewrite.spec.js`
Expected: FAIL — no element with `data-testid="copy-headline"`

- [ ] **Step 3: Write the panel**

Create `resources/js/features/report/RewritePanel.jsx`:

```jsx
import React, { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import client from '../../api/client'

/**
 * The page's own words, with a button that improves them.
 *
 * This is the "Fix" in Detect -> Explain -> Fix. Everything else on this screen
 * tells you what is wrong; this is the only part that hands you something to
 * paste.
 *
 * The `stored` prop is the rewrite that already rode along in the report
 * payload. It is the fallback when a live call fails — step 7 of the demo is a
 * live model call in a venue nobody controls.
 */
const ELEMENTS = [
  { key: 'headline', label: 'Headline' },
  { key: 'subhead',  label: 'Supporting line' },
  { key: 'cta',      label: 'Button label' },
]

export default function RewritePanel({ auditId, section, stored = [] }) {
  const copy = section.copy
  if (!copy) return null

  const textFor = (key) =>
    key === 'cta' ? copy.ctas?.[0]?.text : copy[key]?.text

  const present = ELEMENTS.filter((e) => textFor(e.key))
  if (present.length === 0) return null

  return (
    <div className="mt-4 rounded-lg border border-stone-200 bg-stone-50 p-4">
      <h4 className="text-xs font-semibold uppercase tracking-wide text-stone-500">
        What this section says
      </h4>

      <div className="mt-3 space-y-4">
        {present.map((element) => (
          <Element
            key={element.key}
            auditId={auditId}
            sectionName={section.name}
            element={element}
            original={textFor(element.key)}
            stored={stored.find((r) => r.element === element.key)}
          />
        ))}
      </div>
    </div>
  )
}

function Element({ auditId, sectionName, element, original, stored }) {
  const [result, setResult] = useState(stored ?? null)
  const [fellBack, setFellBack] = useState(false)

  const rewrite = useMutation({
    mutationFn: () =>
      client.post(`/audits/${auditId}/rewrite`, { section: sectionName, element: element.key }),
    onSuccess: (res) => { setResult(res.data.data); setFellBack(false) },
    onError: () => {
      // A dead network must not kill step 7 of the demo. If we already have
      // versions saved from an earlier run, show those and say so.
      if (stored) { setResult(stored); setFellBack(true) }
    },
  })

  return (
    <div>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <span className="block text-xs text-stone-500">{element.label}</span>
          <p data-testid={`copy-${element.key}`} className="text-sm font-medium text-stone-900">
            {original}
          </p>
        </div>

        <button
          type="button"
          onClick={() => rewrite.mutate()}
          disabled={rewrite.isPending}
          className="shrink-0 rounded-md border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-stone-100 disabled:opacity-50"
        >
          {rewrite.isPending ? 'Rewriting…' : 'Rewrite this'}
        </button>
      </div>

      {rewrite.isPending && (
        <div data-testid="rewrite-loading" className="mt-3 space-y-2">
          <div className="h-4 w-3/4 animate-pulse rounded bg-stone-200" />
          <div className="h-3 w-1/2 animate-pulse rounded bg-stone-200" />
        </div>
      )}

      {!rewrite.isPending && rewrite.isError && !result && (
        <p className="mt-2 text-xs text-red-700">
          {rewrite.error?.friendly ?? 'Could not rewrite this just now.'}
        </p>
      )}

      {!rewrite.isPending && result && (
        <div className="mt-3 space-y-2">
          {fellBack && (
            <p className="text-xs text-amber-700">
              Could not reach the model just now — these are the versions saved earlier.
            </p>
          )}

          {result.variants.map((v, i) => (
            <div key={i} className="rounded-md border border-stone-200 bg-white p-3">
              <div className="flex items-start justify-between gap-3">
                <p data-testid="rewrite-variant" className="text-sm font-medium text-stone-900">
                  {v.text}
                </p>
                <button
                  type="button"
                  onClick={() => navigator.clipboard?.writeText(v.text)}
                  className="shrink-0 text-xs text-stone-500 underline hover:text-stone-900"
                >
                  Copy
                </button>
              </div>
              <p data-testid="rewrite-reason" className="mt-1 text-xs text-stone-500">
                {v.reason}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
```

- [ ] **Step 4: Wire it into the report screen**

In `resources/js/pages/Report.jsx`, add the import:

```jsx
import RewritePanel from '../features/report/RewritePanel'
```

Find the block that renders each section card. Add `data-testid="section-card"` to that card's outermost element, and render the panel at the bottom of the card's text column:

```jsx
              <RewritePanel
                auditId={id}
                section={section}
                stored={(report.data.rewrites ?? []).filter((r) => r.section === section.name)}
              />
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `npx playwright test tests/e2e/rewrite.spec.js`
Expected: PASS, 3 tests

If the first test fails on the link name, run `npx playwright test --headed` and read what the seeded page is actually called; adjust the selector to match the seeder rather than changing the seeder.

- [ ] **Step 6: Put the rewrites in the PDF**

In `app/Http/Controllers/ReportPdfController.php`, add `'rewrites'` to the `load()` call:

```php
        $audit->load(['page', 'sections', 'findings', 'recommendations', 'rewrites']);
```

and pass them to the view by changing the `view(...)` line to:

```php
        $rewrites = $audit->rewrites->groupBy('section_name');

        $html = view('pdf.report', compact('audit', 'sections', 'rewrites'))->render();
```

In `resources/views/pdf/report.blade.php`, inside the loop that renders each section, add:

```blade
        @foreach ($rewrites[$section['name']] ?? [] as $rewrite)
            <div class="rewrite">
                <p class="rewrite-label">Suggested {{ $rewrite->element === 'cta' ? 'button label' : $rewrite->element }}</p>
                <p class="rewrite-old">Now: {{ $rewrite->original }}</p>
                @foreach ($rewrite->variants as $variant)
                    <p class="rewrite-new">{{ $variant['text'] }}</p>
                    <p class="rewrite-why">{{ $variant['reason'] }}</p>
                @endforeach
            </div>
        @endforeach
```

Add matching styles to the `<style>` block in that view, following the classes already there.

- [ ] **Step 7: Check the PDF by eye**

```bash
php artisan db:seed --class=DemoAuditSeeder
curl -s -o /tmp/dropsense.pdf "http://localhost:8000/api/audits/1/pdf" && open /tmp/dropsense.pdf
```

Expected: the old copy and the suggested versions are both in the document. This is the part a client can act on without opening the app.

- [ ] **Step 8: Commit**

```bash
git add resources/js/features/report/RewritePanel.jsx resources/js/pages/Report.jsx resources/views/pdf/report.blade.php app/Http/Controllers/ReportPdfController.php tests/e2e/rewrite.spec.js
git commit -m "The Rewrite panel, and its fallback for a dead network

Step 7 of the demo is a live model call in a venue nobody controls, so a
failed call falls back to the versions already stored and says plainly
that it did. The seeded demo page ships with rewrites in place, which is
what makes that fallback real rather than theoretical.

The PDF carries them too: that is the part a client can act on without
opening the app."
```

---

## Task 9: Run Lighthouse in the same browser visit (`#129`)

**Files:**
- Create: `database/migrations/2026_08_03_100004_add_lighthouse_to_audits_table.php`
- Modify: `scripts/capture.mjs`, `package.json`, `app/Models/Audit.php`, `app/Services/Capture/PlaywrightCaptureDriver.php`
- Test: `tests/e2e/capture-lighthouse.spec.js`

**Interfaces:**
- Consumes: nothing
- Produces:
  - The capture JSON gains a top-level `lighthouse` key: `{performance: int, accessibility: int, best_practices: int, seo: int, worst_checks: Array<{id, title, score}>}` or `null`
  - `audits.lighthouse` (json, nullable), cast to `array`
  - Task 10 reads `$audit->lighthouse['performance']` and `['accessibility']`

- [ ] **Step 1: Install Lighthouse**

Run: `npm install --save-dev lighthouse`
Expected: `lighthouse` appears in `devDependencies`

- [ ] **Step 2: Write the failing test**

Create `tests/e2e/capture-lighthouse.spec.js`:

```js
import { test, expect } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'

test('capture returns real Lighthouse scores from the same visit', () => {
  const dir = mkdtempSync(path.join(tmpdir(), 'dropsense-lh-'))
  const file = path.join(dir, 'page.html')

  // Deliberately imperfect: no lang, no alt text, tiny grey-on-white text.
  writeFileSync(file, `<html><body>
    <section style="min-height:400px;width:1000px">
      <h1 style="color:#bbb;font-size:9px">Faint heading</h1>
      <img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=">
      <a href="/x" class="btn">Go</a>
    </section>
    <section style="min-height:400px;width:1000px"><h2>Two</h2><p>y</p></section>
  </body></html>`)

  const out = execFileSync('node', [
    'scripts/capture.mjs',
    '--url', `file://${file}`,
    '--out', path.join(dir, 'shots'),
  ], { encoding: 'utf8', timeout: 180000 })

  const result = JSON.parse(out.trim())

  expect(result.ok).toBe(true)
  expect(result.lighthouse).not.toBeNull()
  expect(typeof result.lighthouse.performance).toBe('number')
  expect(typeof result.lighthouse.accessibility).toBe('number')
  expect(result.lighthouse.accessibility).toBeLessThan(100)   // that page has real problems
  expect(Array.isArray(result.lighthouse.worst_checks)).toBe(true)
})

test('capture still succeeds when Lighthouse is switched off', () => {
  const dir = mkdtempSync(path.join(tmpdir(), 'dropsense-nolh-'))
  const file = path.join(dir, 'page.html')
  writeFileSync(file, '<body><section style="min-height:400px;width:1000px"><h1>Hi</h1><p>x</p></section><section style="min-height:400px;width:1000px"><h2>Two</h2></section></body>')

  const out = execFileSync('node', [
    'scripts/capture.mjs',
    '--url', `file://${file}`,
    '--out', path.join(dir, 'shots'),
    '--no-lighthouse',
  ], { encoding: 'utf8', timeout: 120000 })

  const result = JSON.parse(out.trim())

  // An audit must never die because a Lighthouse run did.
  expect(result.ok).toBe(true)
  expect(result.sections.length).toBeGreaterThan(0)
  expect(result.lighthouse).toBeNull()
})
```

- [ ] **Step 3: Run it and watch it fail**

Run: `npx playwright test tests/e2e/capture-lighthouse.spec.js`
Expected: FAIL — `result.lighthouse` is undefined

- [ ] **Step 4: Add Lighthouse to `capture.mjs`**

At the top of `scripts/capture.mjs`, add to the imports:

```js
import lighthouse from 'lighthouse';
```

and next to the other constants:

```js
const LH_PORT = 9222;            // the debugging port Lighthouse attaches to
const LH_TIMEOUT_MS = 60000;     // it is allowed to fail; it is not allowed to hang
const WORST_CHECKS = 3;
```

Change the browser launch line to open the debugging port:

```js
const browser = await chromium.launch({
  args: ['--no-sandbox', `--remote-debugging-port=${LH_PORT}`],
});
```

Then add this function after `readCopy`:

```js
/**
 * Lighthouse, against the browser we already have open.
 *
 * It is allowed to fail. A timeout or a crash returns null, capture still
 * succeeds, and the score falls back to the old estimates labelled as such —
 * an audit must never die because a Lighthouse run hung.
 *
 * Note on words: Lighthouse calls its individual checks "audits". In this
 * codebase an audit is a row in the audits table, so these are checks.
 */
async function runLighthouse(url) {
  try {
    const result = await Promise.race([
      lighthouse(url, {
        port: LH_PORT,
        output: 'json',
        logLevel: 'silent',
        onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
      }),
      new Promise((_, reject) =>
        setTimeout(() => reject(new Error('Lighthouse timed out')), LH_TIMEOUT_MS)),
    ]);

    const cats = result?.lhr?.categories;
    if (!cats) return null;

    const pct = (c) => (c?.score == null ? null : Math.round(c.score * 100));

    const worst = Object.values(result.lhr.audits ?? {})
      .filter((a) => typeof a.score === 'number' && a.score < 0.9 && a.title)
      .sort((a, b) => a.score - b.score)
      .slice(0, WORST_CHECKS)
      .map((a) => ({ id: a.id, title: a.title, score: Math.round(a.score * 100) }));

    return {
      performance: pct(cats.performance),
      accessibility: pct(cats.accessibility),
      best_practices: pct(cats['best-practices']),
      seo: pct(cats.seo),
      worst_checks: worst,
    };
  } catch {
    return null;
  }
}
```

- [ ] **Step 5: Call it and emit it**

In `scripts/capture.mjs`, immediately after the mobile screenshot block and before the final `console.log`, add:

```js
  // After the screenshots, so a slow Lighthouse run never costs us the pictures.
  const lighthouseScores = args['no-lighthouse'] !== undefined || process.argv.includes('--no-lighthouse')
    ? null
    : await runLighthouse(args.url);
```

Then change the final `console.log` to include it:

```js
  console.log(JSON.stringify({
    ok: true, how, load_ms: loadMs, page_height: pageHeight,
    long_edge: LONG_EDGE, lighthouse: lighthouseScores, sections: captured,
  }));
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `npx playwright test tests/e2e/capture-lighthouse.spec.js`
Expected: PASS, 2 tests. The first will take 40–70 seconds — that is the cost being measured.

- [ ] **Step 7: Write the migration**

Create `database/migrations/2026_08_03_100004_add_lighthouse_to_audits_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            // { performance, accessibility, best_practices, seo, worst_checks[] }
            // Nullable on purpose: a failed Lighthouse run must degrade the score
            // to a labelled estimate, never fail the audit.
            $table->json('lighthouse')->nullable()->after('category_scores');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('lighthouse');
        });
    }
};
```

- [ ] **Step 8: Update the model and the driver**

In `app/Models/Audit.php`, add `'lighthouse'` to `$fillable` and the cast:

```php
        'page_id', 'status', 'stage', 'overall_score', 'category_scores', 'lighthouse',
        'token_cost', 'ai_model', 'error_message', 'completed_at',
```

```php
            'lighthouse'      => 'array',
```

In `app/Services/Capture/PlaywrightCaptureDriver.php`, replace the `$audit->update([...])` block near the end with:

```php
        // Speed and accessibility are measured here because the browser is
        // already open — free at this point, and the score needs both.
        $audit->update([
            'lighthouse'      => $payload['lighthouse'] ?? null,
            'category_scores' => array_merge($audit->category_scores ?? [], [
                'load_ms'   => $payload['load_ms'] ?? null,
                'detection' => $payload['how'] ?? null,
            ]),
        ]);
```

- [ ] **Step 9: Run the whole suite**

Run: `php artisan migrate && php artisan test`
Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add package.json package-lock.json scripts/capture.mjs database/migrations app/Models/Audit.php app/Services/Capture/PlaywrightCaptureDriver.php tests/e2e/capture-lighthouse.spec.js
git commit -m "Run Lighthouse against the browser capture already opened

One browser launch, one process. A separate stage would open a second
browser to re-fetch a page we already have loaded.

It runs after the screenshots and is allowed to fail: a timeout returns
null, the capture still succeeds, and the score falls back to labelled
estimates. Capture goes from about 25s to about 45s, which sits inside the
existing five-second progress polling."
```

---

## Task 10: Performance and accessibility stop being guesses (`#133`, `#137`)

**Files:**
- Modify: `app/Services/HealthScorer.php`, `app/Jobs/RankAndScoreJob.php:43-91`, `app/Http/Resources/AuditReportResource.php`
- Test: `tests/Unit/HealthScorerTest.php` (add cases)

**Interfaces:**
- Consumes: `Audit::$lighthouse` from Task 9
- Produces:
  - `HealthScorer::score(array $categoryScores, array $measured = [])` — the second argument is a list of category keys whose numbers were measured rather than estimated
  - Each entry in the returned `categories` array gains `'measured' => bool`; `'caveat'` is `null` when `measured` is true
  - Task 12 renders `measured`

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/HealthScorerTest.php`:

```php
    public function test_a_measured_category_carries_no_caveat(): void
    {
        $result = (new \App\Services\HealthScorer)->score(
            ['performance' => 71, 'accessibility' => 88],
            measured: ['performance', 'accessibility'],
        );

        $this->assertTrue($result['categories']['accessibility']['measured']);
        $this->assertNull(
            $result['categories']['accessibility']['caveat'],
            'a measured score must not still claim to be an AI approximation',
        );
    }

    public function test_an_unmeasured_category_keeps_its_caveat(): void
    {
        $result = (new \App\Services\HealthScorer)->score(['accessibility' => 88]);

        $this->assertFalse($result['categories']['accessibility']['measured']);
        $this->assertStringContainsString('estimate', $result['categories']['accessibility']['caveat']);
    }

    public function test_the_weights_do_not_move_when_a_score_is_measured(): void
    {
        $scorer = new \App\Services\HealthScorer;

        $estimated = $scorer->score(['performance' => 60, 'accessibility' => 60]);
        $measured  = $scorer->score(['performance' => 60, 'accessibility' => 60], measured: ['performance', 'accessibility']);

        $this->assertSame($estimated['overall'], $measured['overall']);
    }
```

Create the fallback test in the same file:

```php
    public function test_a_dead_lighthouse_run_does_not_kill_the_audit(): void
    {
        // Exactly what RankAndScoreJob produces when audits.lighthouse is null:
        // the two categories fall back to the AI estimates and are labelled.
        $result = (new \App\Services\HealthScorer)->score([
            'cta' => 40, 'ux' => 50, 'ui' => 60, 'trust' => 70,
            'performance' => 55, 'accessibility' => 65,
        ]);

        $this->assertIsInt($result['overall']);
        $this->assertFalse($result['categories']['performance']['measured']);
        $this->assertFalse($result['categories']['accessibility']['measured']);
    }
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=HealthScorerTest`
Expected: FAIL — `Undefined array key "measured"`

- [ ] **Step 3: Teach the scorer about measurement**

In `app/Services/HealthScorer.php`, replace the `CAVEATS` docblock and constant with:

```php
    /**
     * Only true while these numbers are estimates. A category that Lighthouse
     * measured carries no caveat, because there is nothing left to warn about —
     * and deleting a caveat is only honest once the thing it warned about is gone.
     *
     * @var array<string,string>
     */
    public const CAVEATS = [
        'accessibility' => 'An AI estimate based on contrast and font size — not a real accessibility check.',
        'performance'   => 'An estimate based on a single page load, not a full performance check.',
    ];
```

Then replace the `score()` signature and the two `$breakdown[$key] = [...]` blocks:

```php
    /**
     * @param  array<string,int|float|null>  $categoryScores  0–100 per category; omit
     *                                                        or pass null for anything
     *                                                        there was no data to judge.
     * @param  array<int,string>  $measured  category keys whose number was measured
     *                                       rather than estimated.
     * @return array{overall:int|null, categories:array<string,array<string,mixed>>}
     */
    public function score(array $categoryScores, array $measured = []): array
    {
        $breakdown = [];
        $weightedTotal = 0.0;
        $weightUsed = 0;

        foreach (self::WEIGHTS as $key => $weight) {
            $raw = $categoryScores[$key] ?? null;
            $wasMeasured = in_array($key, $measured, true);

            $row = [
                'label'    => self::LABELS[$key],
                'weight'   => $weight,
                'score'    => null,
                'measured' => $wasMeasured,
                'caveat'   => $wasMeasured ? null : (self::CAVEATS[$key] ?? null),
            ];

            if ($raw === null) {
                // Not scored, so it is left out of the average entirely. Treating a
                // missing category as a zero would punish the user for a number we
                // failed to collect.
                $breakdown[$key] = $row;

                continue;
            }

            $clamped = (int) round(max(0, min(100, $raw)));

            $row['score'] = $clamped;
            $breakdown[$key] = $row;

            $weightedTotal += $clamped * $weight;
            $weightUsed += $weight;
        }

        return [
            'overall'    => $weightUsed > 0 ? (int) round($weightedTotal / $weightUsed) : null,
            'categories' => $breakdown,
        ];
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=HealthScorerTest`
Expected: PASS

- [ ] **Step 5: Feed Lighthouse into the job**

In `app/Jobs/RankAndScoreJob.php`, replace the `$result = $scorer->score(...)` line and the two relevant lines of `categoryScores()`.

Replace:

```php
        $result = $scorer->score($this->categoryScores($audit));
```

with:

```php
        [$scores, $measured] = $this->categoryScores($audit);

        $result = $scorer->score($scores, $measured);
```

Change the method signature and its `return`:

```php
    /**
     * Each category is scored from what we actually measured. Anything we could
     * not judge is left null so HealthScorer drops it from the average rather
     * than scoring it zero.
     *
     * @return array{0:array<string,int|null>, 1:array<int,string>} scores, and the
     *                                                             keys that were
     *                                                             measured rather
     *                                                             than estimated.
     */
    private function categoryScores(Audit $audit): array
    {
```

Replace the early return:

```php
        if ($findings->isEmpty()) {
            return [[], []];
        }
```

Replace the final `return [...]` block with:

```php
        // Lighthouse measured these two. When it failed the column is null, so
        // they fall back to the AI estimate and stay labelled as estimates —
        // the score never silently changes meaning.
        $lighthouse = $audit->lighthouse ?? [];
        $measured = [];

        $performance = $penalty('performance');
        if (is_int($lighthouse['performance'] ?? null)) {
            $performance = $lighthouse['performance'];
            $measured[] = 'performance';
        }

        $accessibility = $penalty('accessibility', 'ui');
        if (is_int($lighthouse['accessibility'] ?? null)) {
            $accessibility = $lighthouse['accessibility'];
            $measured[] = 'accessibility';
        }

        return [[
            'cta'           => $cta,
            'ux'            => $ux,
            'ui'            => $visualAverage,
            'trust'         => $penalty('trust'),
            'performance'   => $performance,
            'accessibility' => $accessibility,
        ], $measured];
```

- [ ] **Step 6: Show the worst checks in the report**

In `app/Http/Resources/AuditReportResource.php`, add after `'score'`:

```php
            // The two or three things Lighthouse scored worst, so "71" is
            // something a reader can act on rather than a number to admire.
            'lighthouse' => $this->lighthouse
                ? ['worst_checks' => $this->lighthouse['worst_checks'] ?? []]
                : null,
```

- [ ] **Step 7: Run the whole suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 8: Delete the caveat from the UI where it is now false**

Search for any hard-coded accessibility caveat text in React or the PDF view:

```bash
grep -rn -i "approximation\|not a real accessibility" resources/js resources/views
```

Replace any hard-coded sentence with the `caveat` field from the payload, which is now `null` when the number was measured. If a component renders the caveat unconditionally, wrap it:

```jsx
{category.caveat && <span className="text-xs text-stone-400">{category.caveat}</span>}
```

- [ ] **Step 9: Commit**

```bash
git add app/Services/HealthScorer.php app/Jobs/RankAndScoreJob.php app/Http/Resources/AuditReportResource.php resources/js resources/views tests/Unit/HealthScorerTest.php
git commit -m "Performance and accessibility stop being guesses

Both now come from Lighthouse, so the printed caveat is deleted — but
conditionally, because deleting a warning is only honest once the thing it
warned about has actually gone. With no Lighthouse data the old estimates
come back and the breakdown says estimated rather than measured.

The weights do not move. Only the source of two of the six changed."
```

---

## Task 11: The fifth rule — people click what is not a button (`#134`, `#135`)

**Files:**
- Create: `app/Services/Correlation/Rules/RageClickMismatch.php`, `tests/Unit/Correlation/RageClickMismatchTest.php`
- Modify: `app/Services/Correlation/Support/Metrics.php`, `app/Services/Correlation/CorrelationService.php:26-32,68-76`
- Test: as above

**Interfaces:**
- Consumes: `page_metrics.rage_clicks` / `dead_clicks` from Task 3
- Produces:
  - `Metrics` gains `public array $rageClicks = []` and `public array $deadClicks = []` as the last two constructor parameters, plus `rageClicksFor(string $sectionName): ?int` and `deadClicksFor(string $sectionName): ?int`
  - `RageClickMismatch` implements the existing `Rule` interface; `key()` returns `'rage_click_mismatch'`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Correlation/RageClickMismatchTest.php`:

```php
<?php

namespace Tests\Unit\Correlation;

use App\Services\Correlation\Rules\RageClickMismatch;
use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\Metrics;
use App\Services\Correlation\Support\Section;
use PHPUnit\Framework\TestCase;

class RageClickMismatchTest extends TestCase
{
    private function snapshot(
        ?array $rageClicks,
        float $ctaClickRate = 2.1,
        array $problems = null,
    ): AuditSnapshot {
        return new AuditSnapshot(
            metrics: new Metrics(
                visitors: 18_450,
                bounceRate: 64.2,
                conversionRate: 1.8,
                ctaClickRate: $ctaClickRate,
                sectionReach: ['Features' => 71.0],
                rageClicks: $rageClicks ?? [],
                deadClicks: $rageClicks === null ? [] : ['Features' => 512],
            ),
            sections: [
                new Section(
                    name: 'Features',
                    position: 1200,
                    height: 900,
                    pageHeight: 4500,
                    aiScore: 58,
                    problems: $problems ?? [[
                        'what'     => 'The feature cards lift on hover but nothing happens when you click them.',
                        'why'      => 'A hover effect is a promise that the thing is clickable.',
                        'fix'      => 'Either link the cards or remove the hover effect.',
                        'severity' => 4,
                        'category' => 'ui',
                    ]],
                ),
            ],
        );
    }

    public function test_it_fires_when_a_section_collects_rage_clicks_and_the_button_is_ignored(): void
    {
        $candidate = (new RageClickMismatch)->evaluate($this->snapshot(['Features' => 340]));

        $this->assertNotNull($candidate);
        $this->assertSame('Features', $candidate->sectionName);
        $this->assertSame('rage_clicks', $candidate->evidence['metric']);
        $this->assertSame(340.0, (float) $candidate->evidence['value']);
        $this->assertStringContainsString('340', $candidate->statement);
    }

    /** Both halves are required. Rage clicks on a page whose button works is a different story. */
    public function test_it_stays_quiet_when_the_button_is_doing_fine(): void
    {
        $this->assertNull(
            (new RageClickMismatch)->evaluate($this->snapshot(['Features' => 340], ctaClickRate: 24.0))
        );
    }

    public function test_a_handful_of_rage_clicks_is_not_a_pattern(): void
    {
        $this->assertNull((new RageClickMismatch)->evaluate($this->snapshot(['Features' => 3])));
    }

    /** A null must never be read as a zero — and must never be read as a signal either. */
    public function test_no_rage_click_data_keeps_it_silent(): void
    {
        $this->assertNull((new RageClickMismatch)->evaluate($this->snapshot(null)));
    }

    public function test_it_needs_the_ai_to_have_seen_something_visual_too(): void
    {
        $this->assertNull(
            (new RageClickMismatch)->evaluate($this->snapshot(['Features' => 340], problems: []))
        );
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=RageClickMismatchTest`
Expected: FAIL — `Unknown named parameter $rageClicks`

- [ ] **Step 3: Add the two fields to `Metrics`**

In `app/Services/Correlation/Support/Metrics.php`, add two constructor parameters after `sectionReach`:

```php
        /** @var array<string,int> section name => rage clicks recorded there */
        public array $rageClicks = [],
        /** @var array<string,int> section name => dead clicks recorded there */
        public array $deadClicks = [],
```

and add these methods:

```php
    /** Rage clicks recorded on this section, or null if none were supplied. */
    public function rageClicksFor(string $sectionName): ?int
    {
        return $this->lookUp($this->rageClicks, $sectionName);
    }

    /** Dead clicks recorded on this section, or null if none were supplied. */
    public function deadClicksFor(string $sectionName): ?int
    {
        return $this->lookUp($this->deadClicks, $sectionName);
    }

    /** @param array<string,int> $map */
    private function lookUp(array $map, string $sectionName): ?int
    {
        foreach ($map as $name => $count) {
            if (strcasecmp((string) $name, $sectionName) === 0) {
                return (int) $count;
            }
        }

        return null;
    }
```

- [ ] **Step 4: Write the rule**

Create `app/Services/Correlation/Rules/RageClickMismatch.php`:

```php
<?php

namespace App\Services\Correlation\Rules;

use App\Services\Correlation\Support\AuditSnapshot;
use App\Services\Correlation\Support\InsightCandidate;

/**
 * Visitors click the same spot again and again because nothing happens.
 *
 * Almost always the same cause: something looks clickable and is not — a card
 * with a hover effect and no link, an image that reads as a button. Meanwhile
 * the real button sits there being ignored.
 *
 * This rule was written for V1 and cut, because V1 collected no click data. The
 * demo fixture now supplies it; the live Clarity API behind it is still V2.
 */
class RageClickMismatch implements Rule
{
    /** Below this, a few frustrated clicks are noise rather than a pattern. */
    private const MEANINGFUL_RAGE_CLICKS = 50;

    /** Above this click rate, the button is working and this is a different story. */
    private const HEALTHY_CLICK_RATE = 10.0;

    public function key(): string
    {
        return 'rage_click_mismatch';
    }

    public function evaluate(AuditSnapshot $snapshot): ?InsightCandidate
    {
        $clickRate = $snapshot->metrics->ctaClickRate;

        // Both halves are required. Rage clicks on a page whose button works is a
        // different problem with a different fix.
        if ($clickRate === null || $clickRate > self::HEALTHY_CLICK_RATE) {
            return null;
        }

        foreach ($snapshot->sections as $section) {
            $rage = $snapshot->metrics->rageClicksFor($section->name);

            // Not supplied. Say nothing rather than read a missing number as a zero.
            if ($rage === null || $rage < self::MEANINGFUL_RAGE_CLICKS) {
                continue;
            }

            // The numbers say people are clicking. The picture has to say what
            // they are clicking, or this is an insight that cannot prove itself.
            $problem = $section->worstProblemIn('ui', 'layout', 'cta');
            if ($problem === null) {
                continue;
            }

            $dead = $snapshot->metrics->deadClicksFor($section->name);
            $deadNote = $dead !== null
                ? sprintf(' Another %s clicks landed on nothing at all.', number_format($dead))
                : '';

            return new InsightCandidate(
                ruleKey: $this->key(),
                sectionName: $section->name,
                statement: sprintf(
                    'Visitors clicked %s times in frustration on %s while only %s%% pressed the real button.%s %s People are clicking something here that is not a button — the thing that looks clickable is not, and the thing that is does not look it.',
                    number_format($rage),
                    $section->name,
                    $clickRate,
                    $deadNote,
                    $problem['what'],
                ),
                evidence: [
                    'metric'     => 'rage_clicks',
                    'value'      => (float) $rage,
                    'unit'       => 'clicks',
                    'comparison' => sprintf('only %s%% of visitors press the real button', $clickRate),
                ],
                confidence: 0.85,
                severity: (int) ($problem['severity'] ?? 3),
                sourceProblem: $problem,
            );
        }

        return null;
    }
}
```

- [ ] **Step 5: Run the rule test to verify it passes**

Run: `php artisan test --filter=RageClickMismatchTest`
Expected: PASS, 5 tests

- [ ] **Step 6: Register the rule and map the new metrics**

In `app/Services/Correlation/CorrelationService.php`, add the import:

```php
use App\Services\Correlation\Rules\RageClickMismatch;
```

add it to `rules()`:

```php
            new TrustGapEarly,
            new RageClickMismatch,
```

and add the two fields to the `Metrics` construction in `snapshot()`:

```php
            sectionReach: $m?->section_reach ?? [],
            rageClicks: $m?->rage_clicks ?? [],
            deadClicks: $m?->dead_clicks ?? [],
```

- [ ] **Step 7: Run the whole suite**

Run: `php artisan test`
Expected: PASS — the four existing rule tests still pass because the new `Metrics` parameters have defaults

- [ ] **Step 8: Commit**

```bash
git add app/Services/Correlation tests/Unit/Correlation/RageClickMismatchTest.php
git commit -m "Rule: people click something that is not a button

The fifth rule. Written for V1 and cut because V1 collected no click data;
the demo fixture now supplies it.

Both halves are required — rage clicks alone is not enough, because rage
clicks on a page whose button works is a different problem with a
different fix. A null is silence, never a zero, and the rule still has to
find a visual cause in the AI findings or it produces nothing."
```

---

## Task 12: Make the report screen look like a product (`#139`)

**Files:**
- Create: `resources/js/features/report/theme.js`
- Modify: `resources/js/features/report/ui.jsx`, `resources/js/pages/Report.jsx`, `resources/js/pages/Pages.jsx`, `resources/js/app.jsx`
- Test: `tests/e2e/report-visual.spec.js`

**Interfaces:**
- Consumes: `metrics_source` (Task 3), `copy` (Task 5), `rewrites` (Task 7), `categories[].measured` (Task 10)
- Produces: `theme.js` exports `TOKENS` — a plain object of Tailwind class strings, imported by both screens. No component API changes.

**Before starting:** read the `frontend-design` skill. This task is the one place in the plan where visual judgment matters more than following instructions literally, and "beautiful dashboard" is one of the five stated winning points.

- [ ] **Step 1: Write the failing test**

Create `tests/e2e/report-visual.spec.js`:

```js
import { test, expect } from '@playwright/test'

/** Seed first: php artisan db:seed --class=DemoAuditSeeder */
test.describe('the report screen', () => {
  test('the score, the source of the numbers and the top fixes are all above the sections', async ({ page }) => {
    await page.goto('/audits/1')

    await expect(page.getByTestId('score-dial')).toBeVisible()
    await expect(page.getByText(/conversion score/i)).toBeVisible()

    // Demo numbers are labelled, not hidden.
    await expect(page.getByTestId('metrics-source')).toBeVisible()

    const dial = await page.getByTestId('score-dial').boundingBox()
    const first = await page.getByTestId('section-card').first().boundingBox()
    expect(dial.y).toBeLessThan(first.y)
  })

  test('the score breakdown says which numbers were measured', async ({ page }) => {
    await page.goto('/audits/1')
    await page.getByRole('button', { name: /how this score/i }).click()

    await expect(page.getByTestId('category-accessibility')).toContainText(/measured|estimated/i)
  })

  test('nothing scrolls sideways on a phone', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto('/audits/1')

    const overflow = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth)

    expect(overflow).toBeLessThanOrEqual(1)
  })

  test('every number on screen has a sentence explaining it', async ({ page }) => {
    await page.goto('/audits/1')

    const metrics = page.getByTestId('metric')
    const count = await metrics.count()
    expect(count).toBeGreaterThan(0)

    for (let i = 0; i < count; i++) {
      await expect(metrics.nth(i).getByTestId('metric-explain')).not.toBeEmpty()
    }
  })
})
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan db:seed --class=DemoAuditSeeder && npx playwright test tests/e2e/report-visual.spec.js`
Expected: FAIL — the test ids do not exist

- [ ] **Step 3: Write the tokens**

Create `resources/js/features/report/theme.js`:

```js
/**
 * One place where the look is decided, imported by both screens.
 *
 * Tokens rather than ad-hoc classes, so a change to the palette is one edit
 * instead of forty — and so the Pages screen inherits the identity for free
 * without any bespoke work of its own.
 *
 * Choose the actual values with the frontend-design skill open. What matters is
 * that they are chosen once, deliberately, and reused.
 */
export const TOKENS = {
  page:        'mx-auto max-w-5xl px-5 py-8 sm:py-12',
  card:        'rounded-xl border border-stone-200 bg-white shadow-sm',
  cardPad:     'p-5 sm:p-6',
  heading:     'text-lg font-semibold tracking-tight text-stone-900',
  subheading:  'text-xs font-semibold uppercase tracking-wide text-stone-500',
  body:        'text-sm text-stone-700',
  muted:       'text-xs text-stone-500',
  buttonPrime: 'rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50',
  buttonQuiet: 'rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-stone-100',
}

/** Priority is three buckets only, because more would be noise. */
export const PRIORITY = {
  High:   'bg-red-50 text-red-800 ring-1 ring-red-200',
  Medium: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
  Low:    'bg-stone-100 text-stone-600 ring-1 ring-stone-200',
}
```

- [ ] **Step 4: Design the four things that matter**

Apply the tokens across `ui.jsx`, `Report.jsx`, `Pages.jsx` and `app.jsx`. The four elements worth real design attention — everything else is type and spacing:

1. **`ScoreDial`** in `ui.jsx` — give it `data-testid="score-dial"`, label it **Conversion Score**, and make the arc read at a glance.
2. **The section card** in `Report.jsx` — `data-testid="section-card"`, screenshot left, findings right, the rewrite panel underneath, stacking to one column below `sm`.
3. **The rewrite panel** — already built in Task 8; give it the same card treatment as the rest.
4. **`PriorityTag`** in `ui.jsx` — use the `PRIORITY` map.

- [ ] **Step 5: Add the required test hooks**

While applying the design, add these attributes — the tests depend on them and they cost nothing:

- The metrics strip: each item gets `data-testid="metric"`, and its explanation `data-testid="metric-explain"`
- The source label under the metrics strip: `data-testid="metrics-source"`, rendering `report.metrics_source.label`
- Each category row in the breakdown: `data-testid={`category-${key}`}`, and next to the score render:

```jsx
              <span className={TOKENS.muted}>
                {category.measured ? 'measured' : 'estimated'}
              </span>
```

- The breakdown toggle button must be named so `getByRole('button', { name: /how this score/i })` finds it — e.g. `How this score is built`.

- [ ] **Step 6: Run the test to verify it passes**

Run: `npx playwright test tests/e2e/report-visual.spec.js`
Expected: PASS, 4 tests

- [ ] **Step 7: Look at it at 390px yourself**

Run `npm run dev`, open the report, and use the browser's device toolbar at 390px wide.
Expected: nothing scrolls sideways, no text is smaller than 12px, the score is readable without zooming, and the rewrite panel's buttons are still tappable.

- [ ] **Step 8: Show it to someone who has never seen it**

Ask them to name the single most important thing to fix. Time it.
Expected: under two minutes, unprompted. If it takes longer, the top band is doing too much — cut from it rather than adding explanation.

- [ ] **Step 9: Commit**

```bash
git add resources/js tests/e2e/report-visual.spec.js
git commit -m "Design pass on the report screen

Tokens in one file rather than ad-hoc classes, so the Pages screen
inherits the identity for free — it is on screen for about ten seconds and
does not deserve bespoke work.

The breakdown now says measured or estimated per category, which is the
visible half of the Lighthouse change: a reader can see which of the six
numbers are opinions."
```

---

## Task 13: Seed the demo with its rewrites and Lighthouse scores (`#140`)

**Files:**
- Modify: `database/seeders/DemoAuditSeeder.php`
- Test: `tests/Feature/DemoSeederTest.php`

**Interfaces:**
- Consumes: `config('demo-analytics')` (Task 2), the `copy` shape (Task 4), `Rewrite` (Task 6), the `lighthouse` shape (Task 9)
- Produces: a completed audit whose report renders with no network access at all

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DemoSeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Audit;
use Database\Seeders\DemoAuditSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeded audit is the insurance policy for demo day. If it needs a network,
 * a reachable URL and Chromium all behaving at once, it is not insurance.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_produces_a_finished_audit(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $audit = Audit::latest('id')->first();

        $this->assertSame(Audit::STATUS_COMPLETED, $audit->status);
        $this->assertIsInt($audit->overall_score);
    }

    public function test_the_seeded_numbers_are_the_fixture_numbers(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $metrics = Audit::latest('id')->first()->metrics;

        $this->assertSame(config('demo-analytics.visitors'), $metrics->visitors);
        $this->assertSame('demo', $metrics->source);
        $this->assertNotNull($metrics->rage_clicks);
    }

    public function test_the_sections_carry_the_pages_words(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $hero = Audit::latest('id')->first()->sections()->where('viewport', 'desktop')->first();

        $this->assertNotNull($hero->copy['headline']['text'] ?? null);
        $this->assertNotEmpty($hero->copy['ctas'] ?? []);
    }

    /** This is the whole wifi insurance: the rewrites exist before anyone clicks. */
    public function test_the_rewrites_are_already_stored(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $rewrites = Audit::latest('id')->first()->rewrites;

        $this->assertGreaterThanOrEqual(2, $rewrites->count());
        $this->assertGreaterThanOrEqual(2, count($rewrites->first()->variants));
        $this->assertNotEmpty($rewrites->first()->variants[0]['reason']);
    }

    public function test_lighthouse_scores_are_present_so_the_breakdown_says_measured(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $audit = Audit::latest('id')->first();

        $this->assertIsInt($audit->lighthouse['performance']);
        $this->assertIsInt($audit->lighthouse['accessibility']);
        $this->assertTrue($audit->category_scores['accessibility']['measured']);
    }

    public function test_the_fifth_rule_fired(): void
    {
        $this->seed(DemoAuditSeeder::class);

        $this->assertTrue(
            Audit::latest('id')->first()->insights->contains('rule_key', 'rage_click_mismatch'),
            'the seeded numbers must demonstrate the rage-click rule on stage',
        );
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=DemoSeederTest`
Expected: FAIL — no rewrites, no lighthouse

- [ ] **Step 3: Read the seeder before changing it**

Run: `cat database/seeders/DemoAuditSeeder.php`

It already builds a page, an audit, metrics, sections, findings, then runs `CorrelationService`, `RecommendationEngine` and `HealthScorer`. You are adding four things, not rewriting it.

- [ ] **Step 4: Read the metrics from the fixture**

Replace the hard-coded `$audit->metrics()->create([...])` array with:

```php
        // The same file the endpoint serves, so the stage and the seed cannot drift.
        $demo = config('demo-analytics');

        $audit->metrics()->create([
            'visitors'           => $demo['visitors'],
            'bounce_rate'        => $demo['bounce_rate'],
            'conversion_rate'    => $demo['conversion_rate'],
            'cta_click_rate'     => $demo['cta_click_rate'],
            'mobile_share'       => $demo['mobile_share'],
            'mobile_bounce_rate' => $demo['mobile_bounce_rate'],
            'section_reach'      => $demo['section_reach'],
            'rage_clicks'        => $demo['rage_clicks'],
            'dead_clicks'        => $demo['dead_clicks'],
            'source'             => 'demo',
        ]);
```

- [ ] **Step 5: Give each seeded section its words**

In the loop (or array) that creates the sections, add a `copy` key. Use a map keyed by section name so the words match the story the numbers tell:

```php
        $copyFor = [
            'Hero' => [
                'headline' => ['text' => 'Welcome to our platform', 'tag' => 'h1', 'selector' => 'h1'],
                'subhead'  => ['text' => 'We help businesses grow.', 'tag' => 'p', 'selector' => 'p.lead'],
                'ctas'     => [['text' => 'Submit', 'tag' => 'button', 'selector' => 'button.cta']],
            ],
            'Features' => [
                'headline' => ['text' => 'Our features', 'tag' => 'h2', 'selector' => 'h2'],
                'subhead'  => ['text' => 'Everything you need in one place.', 'tag' => 'p', 'selector' => 'p'],
                'ctas'     => [],
            ],
            'Pricing' => [
                'headline' => ['text' => 'Pricing', 'tag' => 'h2', 'selector' => 'h2'],
                'subhead'  => ['text' => 'Plans for every team.', 'tag' => 'p', 'selector' => 'p'],
                'ctas'     => [['text' => 'Learn more', 'tag' => 'a', 'selector' => 'a.btn']],
            ],
        ];
```

and pass `'copy' => $copyFor[$name] ?? null,` into each `sections()->create([...])` call. Match the variable the seeder already uses for the section name.

The seeded headline is deliberately weak — "Welcome to our platform" with a button that says "Submit". A rewrite of good copy demonstrates nothing.

- [ ] **Step 6: Add the Lighthouse block**

Immediately before the seeder runs `HealthScorer` (or before the final `$audit->update([...])`), add:

```php
        // Plausible scores for a page with the problems this demo describes.
        $audit->update(['lighthouse' => [
            'performance'    => 64,
            'accessibility'  => 78,
            'best_practices' => 83,
            'seo'            => 92,
            'worst_checks'   => [
                ['id' => 'color-contrast', 'title' => 'Background and foreground colours do not have a sufficient contrast ratio', 'score' => 0],
                ['id' => 'largest-contentful-paint', 'title' => 'Largest Contentful Paint', 'score' => 41],
                ['id' => 'unused-javascript', 'title' => 'Reduce unused JavaScript', 'score' => 55],
            ],
        ]]);
```

If the seeder calls `HealthScorer::score()` directly, pass the second argument so the breakdown says *measured*:

```php
        $result = $scorer->score($categoryScores, measured: ['performance', 'accessibility']);
```

- [ ] **Step 7: Store the rewrites**

At the end of `run()`, after the audit is marked completed, add:

```php
        // The whole wifi insurance. These exist before anyone clicks, so a dead
        // network on stage degrades the rewrite panel to a fallback rather than
        // an error.
        $audit->rewrites()->create([
            'section_name' => 'Hero',
            'element'      => 'headline',
            'original'     => 'Welcome to our platform',
            'variants'     => [
                ['text' => 'Cut your reporting time from days to minutes', 'reason' => 'Names the outcome instead of greeting the visitor — and 96% of them reach this line, so it is the highest-leverage sentence on the page.'],
                ['text' => 'Every number your team argues about, in one place', 'reason' => 'Replaces a general claim with a specific problem a reader recognises.'],
                ['text' => 'Stop rebuilding the same report every Monday', 'reason' => 'Opens on the reader\'s frustration rather than the product, which suits a page with a 64% bounce rate.'],
            ],
            'model'  => 'seeded',
            'tokens' => 0,
        ]);

        $audit->rewrites()->create([
            'section_name' => 'Hero',
            'element'      => 'cta',
            'original'     => 'Submit',
            'variants'     => [
                ['text' => 'Start free', 'reason' => '96% of visitors reach this button and 2.1% press it — "Submit" describes the form, not what the visitor gets.'],
                ['text' => 'See your first report', 'reason' => 'Names the reward rather than the mechanism.'],
                ['text' => 'Try it on your data', 'reason' => 'Removes the sense of commitment that suppresses a first click.'],
            ],
            'model'  => 'seeded',
            'tokens' => 0,
        ]);
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --filter=DemoSeederTest`
Expected: PASS, 6 tests

If `test_the_fifth_rule_fired` fails, the seeded findings carry no `ui`, `layout` or `cta` problem on the Features section. Add one to the seeded findings — the rule needs a visual cause or it correctly produces nothing.

- [ ] **Step 9: Prove it works with the network off**

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoAuditSeeder
```

Turn off wifi. Open the seeded report and click **Rewrite this** on the hero headline.
Expected: the stored versions appear and the panel says it could not reach the model.

- [ ] **Step 10: Commit**

```bash
git add database/seeders/DemoAuditSeeder.php tests/Feature/DemoSeederTest.php
git commit -m "Seed the demo with its rewrites and Lighthouse scores

The seeded audit is the insurance policy for demo day, and it was written
before either feature existed. It now reads its numbers from the same
config file the endpoint serves, carries the page's words, ships with
rewrites already stored, and has a Lighthouse block so the breakdown says
measured.

The seeded headline is deliberately weak. A rewrite of good copy
demonstrates nothing."
```

---

## Task 14: Run the whole thing, twice, on real pages (`#141`)

**Files:**
- Modify: whatever the run exposes
- Test: the demo itself

This task has no code. It is the shared Definition of Done from `doc/02-definition-of-done.md`, executed rather than asserted.

- [ ] **Step 1: Run the full suite**

Run: `php artisan test && npx playwright test`
Expected: everything passes. Paste the summary lines into card `#141` before continuing — a claim of "tests pass" without the output is not evidence.

- [ ] **Step 2: Audit four real landing pages**

Pick four you did not use while building. Set `AI_DRIVER=claude`, `AI_REWRITE_DRIVER=claude`, `CAPTURE_DRIVER=playwright`, then for each: add the page, press Run audit without touching the pre-filled form, and read the report as a judge would.

For each page, write one line in card `#141` answering: **would you act on the top fix?** If the answer is no for two or more of the four, the problem is the prompt, and prompt tuning is what the remaining time is for.

- [ ] **Step 3: Read four rewrites critically**

On each page, rewrite the hero headline and the main button.

Expected: the reason cites the critique or a number. If the reasons read as general copywriting advice ("more compelling", "clearer value proposition"), the third input is not reaching the model — check `RewritePrompt::insightFor()` is finding an insight for that section.

- [ ] **Step 4: Walk the unhappy paths**

- Point an audit at a URL that does not resolve → a readable message and a **Try again** button, never a stack trace, never stuck on `running`
- Turn the wifi off and click **Rewrite this** on the seeded page → stored versions plus the fallback notice
- Open a report at 390px wide → no sideways scroll, everything tappable
- Kill the queue worker mid-audit, restart it → the audit either completes or fails cleanly

- [ ] **Step 5: Check the numbers are labelled**

On a demo-data audit, confirm the report header says the numbers are demo data. Then type one real number over a field and confirm it flips to saying they are yours.

- [ ] **Step 6: Export the PDF**

Expected: two to three pages, the Conversion Score, the top fixes, the section pictures, and the rewrites with old and new copy side by side.

- [ ] **Step 7: Rehearse twice without a terminal**

Both runs, start to finish, touching nothing but the browser. Time them. If a live capture takes longer than the story you tell over it, open on the seeded audit and run the live one second.

- [ ] **Step 8: Update the docs in the same commit as any fix**

Anything you changed in steps 1–7 gets its feature doc updated in the same commit. That is the shared DoD, and it is the rule that stops the docs quietly going stale.

- [ ] **Step 9: Close the cards**

```bash
kanban-md move 141 done
kanban-md list --compact --tag dropsense --status backlog   # anything left is a decision, not an oversight
```

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| §1 rename, user-facing only | Task 1 |
| §2 pipeline unchanged; capture absorbs both | Tasks 4, 9 |
| §3 fixture pre-fills the form, source labelled | Tasks 2, 3 |
| §4 crawl → rewrite, three inputs, fallback | Tasks 4, 5, 6, 7, 8 |
| §5 Lighthouse, caveat deleted, failure survivable | Tasks 9, 10 |
| §6 fifth rule | Task 11 |
| §7 data model — 3 columns + 1 table | Tasks 3, 5, 6, 9 |
| §8 two endpoints, report stays one response | Tasks 2, 7 |
| §9 report screen redesigned | Tasks 8, 12 |
| §10 four new tests | Tasks 6, 7, 10, 11 |
| §11 the week | Tasks 1–14, in order |
| §12 risks | Task 4 step 6 (crawl on a real page), Task 8 (wifi), Task 9 (Lighthouse timeout), Task 14 (rewrites read as generic) |

**Type consistency checked:** `RewriteResult`, `CopyRewriter::rewrite()`, `RewriteService::forElement()`, `Rewrite::ELEMENTS`, the `copy` shape and the `lighthouse` shape are each defined once and referenced identically downstream. `HealthScorer::score()`'s new second parameter is added in Task 10 and consumed in Tasks 10 and 13 only.

**Known ordering constraint:** Tasks 6–8 depend on Tasks 4–5. Tasks 9–10 are independent of both and can run in parallel with them if two people are working. Task 11 depends only on Task 3. Tasks 12–14 depend on everything.
