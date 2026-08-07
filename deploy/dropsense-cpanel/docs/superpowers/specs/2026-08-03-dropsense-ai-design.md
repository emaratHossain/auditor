# DropSense AI — Hackathon Design

**Date:** 2026-08-03
**Build window:** 7 days. Demo Sunday 9 August.
**Supersedes:** [`2026-08-02-landing-page-auditor-v1-design.md`](2026-08-02-landing-page-auditor-v1-design.md) — that spec is still accurate for everything it describes; this one records what changed.
**Source plan:** `DropSense_AI_Hackathon_Project_Plan.docx`

---

## 1. What changed, and what did not

V1 shipped: capture → one vision call → four PHP correlation rules → ranked
recommendations → health score → two React screens → PDF. That pipeline is not
being rebuilt. DropSense AI is V1 plus three capabilities and a face.

| From the DropSense plan | Decision |
|---|---|
| Rename to **DropSense AI** | User-facing text and docs only. Class names, tables and routes keep their current names |
| **HTML crawl** | In scope — absorbed into the existing capture stage |
| **Lighthouse** | In scope — absorbed into the existing capture stage |
| **Demo GA4 dataset** | In scope — pre-fills the existing metrics form, still editable |
| **Demo Clarity dataset** | In scope — revives the rage-click rule cut from V1 |
| **AI rewrite** of hero and CTA | In scope — the headline new feature |
| **JS tracker (mock)** | **Cut.** The plan itself marks it mock-now, real-later. It produces no data a judge can see on stage |
| **Beautiful dashboard** | In scope — one design day, on the Report screen only |
| **Conversion Score** | The V1 health score, renamed in the UI. Same six categories, same weights |

The V1 spec's core commitments carry over unchanged: the evidence guarantee, the
calculated priority formula, one report endpoint, no blank screens, no internal
vocabulary in the UI.

**One correction to the V1 spec while we are here.** Its stack section names PostgreSQL,
Redis and Horizon. The application that got built runs on **SQLite and the database queue
driver**, and that is what stays — a hackathon week is not when you migrate a working
queue. Nothing in this design needs Postgres; the JSON columns it adds work in SQLite.

> **Amended 2026-08-06.** The database is now **MySQL** — `auditor` for development,
> `auditor_test` for the suite. The paragraph above is left as written because it records
> why the choice was made at the time. Only the engine changed: the queue, cache and
> sessions still run on database tables, so the "no Redis, no Horizon" half of it holds,
> and the JSON columns moved across unaltered.

### The rename

`HealthScorer` stays `HealthScorer`. The `audits` table stays `audits`. Renaming a
working pipeline mid-week is a day of churn with a real chance of breaking the demo
path, and no judge reads the namespace. What changes is every string a human sees:
app name, page titles, PDF header, README, feature docs, board. **"Health score"
becomes "Conversion Score"** in the UI.

---

## 2. The pipeline does not grow a stage

```
CaptureScreenshotsJob → AnalyzePageJob → CorrelateJob → RankAndScoreJob
   ↑ + HTML crawl                           ↑ + RageClickMismatch (5th rule)
   ↑ + Lighthouse
```

**Why capture absorbs both new data sources.** `scripts/capture.mjs` already launches
Chromium, hides cookie banners and walks up to six detected sections. Reading the
headline and CTA text out of those same elements is one `page.evaluate` on a page that
is already loaded. Lighthouse attaches over CDP to that same browser once the
screenshots are taken. One browser launch, one process, one JSON payload back to PHP.

A separate crawl stage would open a second browser to re-fetch a page we already have
open, and a separate Lighthouse stage would open a third.

**Cost:** capture goes from roughly 25s to roughly 45s. Lighthouse is the slow part.
This sits inside the existing poll-every-5s progress flow, so nobody watches a frozen
screen — the `stage` field just reads `capturing` for longer.

**The rewrite is deliberately outside the chain.** It runs on click, after the report
exists, against its own endpoint. In the pipeline it would bill for rewrites nobody
reads, and it would take the live moment out of step 7 of the demo flow.

---

## 3. Metrics: a fixture that pre-fills the form

The demo flow is paste URL → Analyze, with no typing. It is **not** achieved by
deleting the metrics form.

`POST /api/pages` returns the page; React immediately fetches
`GET /api/demo-metrics` and drops the values into the seven-field form **already
filled in**. The user presses Analyze. Zero typing, and the numbers are visible on
screen and overwritable, so the tool can still be pointed at a real page with real
numbers.

The fixture lives in `config/demo-analytics.php` — one file, served by the endpoint
and read by the demo seeder, so what is shown on stage and what ships in the seed
cannot drift apart.

`page_metrics.source` records `demo` or `manual`. **The report header prints which.**
An insight still names a metric, a number and a section, so the evidence guarantee
holds; the honesty comes from labelling the source rather than from hiding it.

The fixture gains two Clarity fields on top of the seven GA4-shaped ones:

| Field | Shape | Feeds |
|---|---|---|
| `rage_clicks` | json, per section | `RageClickMismatch` |
| `dead_clicks` | json, per section | `RageClickMismatch` |

---

## 4. HTML crawl → AI rewrite

### The crawl

Inside each detected section, `capture.mjs` records:

- the first heading
- the paragraph that follows it
- every button or link that reads as a call to action — `<button>`, or `<a>` with
  button-ish classes or a short imperative label

Each entry keeps its text, its tag and a CSS selector. **Capped at one headline, one
subhead and three CTAs per section.** The cap matters: a footer with forty links must
not become forty rewrite targets.

Stored as a `copy` JSON column on `screenshot_sections`. No new table — crawled copy
is an attribute of a section already recorded.

**Fallback.** Marketing pages built in Webflow or React often have no `<h1>` where you
would expect one. When no heading is found, the crawl takes the largest text node by
rendered font size within the section. Less precise, never empty.

### The rewrite

`POST /api/audits/{id}/rewrite` with `{section, element}`.

The service sends the model three things: the original text, the AI's own critique of
that section, and the correlation insight attached to it. **That third input is what
makes this DropSense rather than a thesaurus** — the rewrite is told why the current
copy is failing, in numbers.

Returns 2–3 variants, each with a one-line reason. Text-only call, roughly a tenth of
a cent, behind the same `AI_DRIVER` switch as the vision call, validated by a schema
the same way `AuditSchema` validates the vision reply.

Persisted in a new `rewrites` table, so a second click is free, the PDF can carry the
rewrites, and **the seeded demo page ships with rewrites already stored**. If the live
call fails, the UI shows the stored variants and says the live call failed. That is
the venue-wifi insurance.

---

## 5. Lighthouse

Run in-process after the screenshots, against the already-open Chromium
(`--remote-debugging-port`). Store `{performance, accessibility, best_practices, seo}`
plus the two or three worst-scoring Lighthouse checks, as a `lighthouse` JSON column on
`audits`. (Lighthouse calls its individual checks "audits" too — in this codebase an
*audit* is always a row in the `audits` table, and Lighthouse's are *checks*.)

Two categories stop being guesses:

| Category | Weight | Before | After |
|---|---|---|---|
| Performance | 10% | Playwright load timings | Lighthouse performance score |
| Accessibility | 10% | AI judgment of contrast and font size | Lighthouse accessibility score |

Weights do not move. The line in the report reading *"the accessibility score is an AI
approximation, not a real audit"* is deleted, because it stops being true.

**Failure is not fatal.** If Lighthouse times out or errors, those two categories fall
back to V1 behaviour and the score breakdown says `estimated` rather than `measured`.
An audit never dies because a Lighthouse run hung.

---

## 6. The fifth correlation rule

`RageClickMismatch` fires when a section collects rage or dead clicks while its actual
CTA click rate stays low, and states:

> People are clicking something on this section that isn't a button. The thing that
> looks clickable isn't, and the thing that is doesn't look it.

Pure function over `(AiFinding[], Metrics, Section[])` like the other four. One unit
test. Passes through the same `EvidenceGuarantee` — no rage-click number, no insight.

This was V1's card `#118`, deferred because V1 collected no Clarity data. The demo
fixture now supplies it. The **live** Clarity API stays V2.

---

## 7. Data model — three columns and one table

| Table | Change |
|---|---|
| `screenshot_sections` | `+ copy` (json, nullable): headline, subhead, ctas[] with text + selector |
| `audits` | `+ lighthouse` (json, nullable): four scores plus the worst-scoring checks |
| `page_metrics` | `+ rage_clicks`, `+ dead_clicks` (json, nullable) |
| `rewrites` | **new**: `audit_id`, `section_name`, `element`, `original`, `variants` (json), `model`, `tokens` |

Four migrations, all additive. Nothing existing is altered destructively, so a
mid-week mistake never costs the working pipeline.

---

## 8. API — two endpoints added

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/demo-metrics` | The fixture that pre-fills the metrics form |
| `POST` | `/api/audits/{id}/rewrite` | `{section, element}` → 2–3 variants, persisted |

`GET /api/audits/{id}/report` stays **one** response and grows to carry `copy`,
`lighthouse` and any stored rewrites. Still one round trip for the whole screen.
Splitting it into tidy endpoints makes the dashboard visibly slower — this was true in
V1 and is more true now that the payload is larger.

Conventions unchanged: thin controllers, never `return $model`, signed URLs for
screenshots, `{ message, errors? }` with a real status on every failure.

---

## 9. The Report screen, redesigned

Same single scroll, four bands, now with a visual identity rather than default
Tailwind:

1. **Conversion Score** dial, the metrics strip with its source labelled, and the
   three highest-priority fixes. Someone who reads only this far still knows what to do.
2. **Section cards** — screenshot left, findings right. Where a section has crawled
   copy, its headline and CTA appear as *text* with a **Rewrite this** button. Clicking
   swaps in 2–3 variants, each with its reason underneath and a copy button.
3. The full ranked list, grouped High / Medium / Low.
4. Score breakdown — six categories, Lighthouse's two marked *measured* rather than
   *estimated* — and the PDF button.

The design day is spent here. The Pages screen inherits the same tokens and gets no
bespoke work; it is on screen for about ten seconds.

Rules unchanged: no blank white areas (skeleton, empty state, error state with retry,
every time), every metric carries a one-line explanation, no internal vocabulary.

---

## 10. Testing — four additions

| Kind | Target | Why |
|---|---|---|
| Unit | `RageClickMismatch` | Fifth rule, same bar as the other four |
| Unit | Rewrite schema validation | A malformed model reply is rejected, not half-saved |
| Unit | Lighthouse fallback | A failed Lighthouse run degrades the score, it does not fail the audit |
| Feature | `POST /api/audits/{id}/rewrite` | Contract the report screen depends on |

Everything in the V1 test table still runs and still has to pass.

---

## 11. The week

| Day | Ships | Done when |
|---|---|---|
| 1 (Aug 3) | Rebrand, demo fixture (GA4 **and** Clarity fields), pre-filled form | Paste a URL, the form is already filled, Analyze runs — no typing anywhere |
| 2 (Aug 4) | HTML crawl in `capture.mjs`, stored per section | The report shows the page's real headline and CTA as text |
| 3 (Aug 5) | Rewrite table, service, endpoint, UI panel | Click Rewrite, get three grounded alternatives with reasons |
| 4 (Aug 6) | Lighthouse + score rewiring | Performance and Accessibility are measured numbers; the caveat is gone |
| 5 (Aug 7) | `RageClickMismatch`, all four new tests | Five rules green, whole suite green |
| 6 (Aug 8) | Design pass on the Report screen | Someone who has never seen the tool names the top fix in under two minutes |
| 7 (Aug 9) | Four real pages, seed the demo, rehearse twice | The demo runs end to end twice without touching a terminal |

---

## 12. Risks

| Risk | Mitigation |
|---|---|
| The crawl finds no real headline or CTA on a modern marketing page, and days 2–3 both depend on it | Largest-rendered-text fallback; the existing optional CSS-selector field on the page record is the manual escape hatch |
| Live rewrite call fails on stage | Seeded demo page ships with rewrites already stored; the UI falls back to them and says so |
| Lighthouse hangs or blows memory on the demo machine | Timeout, then fall back to V1 scoring with the breakdown marked `estimated` |
| Capture at ~45s feels slow on stage | Seeded example audit opens instantly; the live run is the second half of the demo, not the first |
| Rewrites read as generic marketing copy | They are fed the critique and the insight, not just the original text. Day 7's four real pages is where this gets judged honestly |
| "Demo data" reads as fake to a judge | It is labelled on screen rather than hidden, and the form stays editable so a real number can be typed in live if challenged |

---

## 13. Feature map

Eleven V1 features, one added, four amended.

| # | Feature | Change |
|---|---|---|
| F00 | Setup and app shell | Rebranded to DropSense AI |
| F01 | Landing pages | Unchanged |
| F02 | Metrics input | Pre-filled from the demo fixture; Clarity fields added |
| F03 | Audit pipeline | Unchanged — still four stages |
| F04 | Capture | **+ HTML crawl, + Lighthouse** |
| F05 | AI vision | Unchanged |
| F06 | Correlation | **+ `RageClickMismatch`** — five rules |
| F07 | Recommendations | Unchanged |
| F08 | Score | Renamed Conversion Score; Performance and Accessibility now measured |
| F09 | Report screen | **Design pass + rewrite panel** |
| F10 | PDF export | Carries rewrites |
| **F11** | **AI copy rewrite** | **New** |
