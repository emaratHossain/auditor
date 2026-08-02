# AI Landing Page UX Auditor — V1 (Hackathon) Design

**Date:** 2026-08-02
**Build window:** 7 days at home. Demo the following Sunday.
**Source plan:** `doc/ai-landing-page-ux-auditor-laravel-plan.html` (10 phases, 7 weeks, ~13 AI calls per audit)

This spec is the hackathon reduction of that plan. Three constraints drove every cut:
lowest possible AI cost, a demo that reads as informative and useful, and a V1 small
enough to finish in a week.

---

## 1. What V1 does

Paste a landing page URL. Type in seven numbers from your analytics. Two to four
minutes later, get a ranked list of fixes where **every item names a real metric, a
real number, and a real section of the page**.

That evidence pairing is the whole product. Everything before it is data collection;
everything after it is presentation.

### In V1

- Add a landing page (name + URL)
- Enter metrics by hand on a short form
- Automatic screenshot capture of up to 6 page sections, desktop + one mobile shot
- One AI vision pass over the whole page
- Four PHP correlation rules joining numbers to visual causes
- Ranked recommendations with evidence, expected impact, and a calculated priority
- A health score out of 100 from six weighted categories
- A two-screen React dashboard
- Server-rendered PDF export

### Deliberately not in V1

| Cut | Why | Where it goes |
|---|---|---|
| Login / Sanctum / multi-tenant | ~1 day of work, invisible on stage | Tech-debt card |
| GA4 Data API | Service account + property permissions + quota; highest-risk, lowest-demo-value work in the plan | V2 card |
| Microsoft Clarity API | Plan itself flags it as narrower than its dashboard | V2 card |
| Score history / trend chart | Needs 2+ audits of the same page over time — cannot be shown honestly in week 1 | V2 card |
| Weekly scheduler + email alerts | Nobody waits a week during a demo | V2 card |
| Analytics screen, Tasks screen | Report screen carries the demo alone | V2 card |
| S3 + 90-day lifecycle | Local disk is enough for one week | Tech-debt card |
| `RageClickMismatch` rule | Needs Clarity data V1 no longer collects | V2 card |

---

## 2. The AI cost decision

The source plan sends 6 sections × 2 viewports = 12 vision calls plus one whole-page
correlation call — 13 calls per audit, re-sending the ~1.5k-token rubric each time.

**V1 sends one call per audit.** All section images, the mobile shot, the metrics
block, and the measured section positions go in a single message. The model sees the
whole page at once, which is what the plan's separate correlation call was paying for.

| Shape | Calls | ~Cost per audit |
|---|---|---|
| Plan as written (Sonnet per section + Opus correlation) | 13 | ~$0.22 |
| **V1: one Sonnet 5 call** | **1** | **~$0.055** |
| Same on Haiku 4.5 | 1 | ~$0.028 |

Two cost levers, neither of which costs quality:

1. **Batch, don't loop.** The rubric prompt is sent once instead of thirteen times.
2. **Downsample screenshots to 1568px on the long edge before upload.** High-res
   images cost up to ~4,784 tokens each; at 1568px it is ~1,600. Judging button
   contrast and copy clarity does not need the extra pixels.

The correlation rules run in PHP and cost nothing.

### Driver abstraction

The vision call sits behind a `VisionAnalyzer` interface with two thin drivers sharing
one prompt and one JSON schema:

- `GeminiVisionAnalyzer` — Gemini 2.5 Flash free tier, used during the build week
- `ClaudeVisionAnalyzer` — Sonnet 5, the demo default

Selected by `AI_DRIVER` in `.env`. Only the HTTP client differs (~40 lines each).

**Open risk:** the model writing the critique *is* the demo. Whichever driver is
presented with must be run against 3–4 real landing pages before demo day and the
output read as a judge would read it. This is the one quality gate that cannot be
fixed the night before.

---

## 3. Metrics come from a form

GA4 does support CSV export of reports, but its export format carries comment header
lines and shifting column names, and — more importantly — it does not contain the two
numbers the correlation rules care about most: per-section scroll depth and CTA click
rate (which requires a GA4 event most landing pages never set up).

So V1 asks for the numbers directly, at audit time (metrics change per audit; the page
record does not carry them).

| Field | Required | Feeds |
|---|---|---|
| `visitors` | yes | Traffic share in the priority formula |
| `bounce_rate` | yes | `TrustGapEarly`, UX category |
| `conversion_rate` | yes | Context in the report header |
| `cta_click_rate` | no | `SeenButNotClicked`, CTA category |
| `mobile_share` | no | `MobileGap` |
| `mobile_bounce_rate` | no | `MobileGap` |
| `section_reach` (% per named section) | no | `DropOffBeforeSection`, traffic share |

**A blank field switches off the rules that need it. It never produces a guess.**
Where a number is missing, the report says "not measured".

A database seeder ships one realistic pre-filled example page so there is a
guaranteed-working audit to open on stage without depending on live typing or a live URL.

---

## 4. The pipeline — eight stages become four

```
CaptureScreenshotsJob → AnalyzePageJob → CorrelateJob → RankAndScoreJob
```

One `Bus::chain()`. Each stage needs the previous stage's rows, and a failure must stop
the rest rather than produce a half-audit that looks complete.

| Stage | What happens | `audits.stage` value |
|---|---|---|
| 1. Capture | Node/Playwright opens the URL, detects up to 6 sections, shoots desktop crops + one full-page mobile shot, converts to WebP at 1568px, records each section's Y-offset and height | `capturing` |
| 2. Analyse | One vision call: all images + metrics + section positions → per-section `{score, problems[]}` where each problem carries `what`, `why`, `fix`, `severity` | `analysing` |
| 3. Correlate | Four PHP rules over (AI findings × metrics) → insights, each carrying evidence | `correlating` |
| 4. Rank & score | Insights → recommendations with calculated priority; six weighted categories → one score | `scoring` |

`FetchAnalyticsJob` is gone — the form supplies metrics. `RecommendJob` needs no second
AI call: the vision response already carries `problem` and `fix` per section, the rules
supply the evidence, and priority is arithmetic.

### Section detection, three levels in order

1. CSS selectors the user typed in (optional field on the page record)
2. `<section>` tags and large landmark blocks
3. Equal horizontal bands labelled by position

Cap at 6 regardless, so cost and latency are bounded.

### Progress

`POST /api/pages/{id}/audits` returns `201 {id, status: "pending"}` immediately.
React polls `GET /api/audits/{id}` every 5 seconds and stops on `completed` or `failed`.
The `stage` field makes the progress bar say something true.

**No audit may sit on `running` forever.** Every job has a timeout; the chain has a
`catch()` that writes `status = failed` plus a human-readable `error_message`, and the
Report screen offers a retry.

---

## 5. The correlation engine

Rules first because a rule can be unit-tested and an opinion cannot. All four are pure
functions over `(AiFinding[], PageMetrics, SectionPosition[])`.

| Rule | Fires when | Produces |
|---|---|---|
| `SeenButNotClicked` | CTA section reach is high, `cta_click_rate` is low | "People find the button and ignore it. Fix the button, not the traffic." |
| `DropOffBeforeSection` | A section sits below the point most visitors stop scrolling | "This section is buried. Move it up or add a second CTA earlier." |
| `MobileGap` | `mobile_bounce_rate` materially exceeds desktop, and the AI flagged a mobile-specific problem | "The mobile layout is the leak. Fix mobile first." |
| `TrustGapEarly` | AI found no trust signal above the fold and bounce rate is high | "Visitors leave before believing you. Add proof early." |

### The evidence guarantee

An insight without **a metric, a number, and a section name** is discarded, not shown.
This is enforced in one place — `CorrelationService::filterUnevidenced()` — and it has
its own unit test. It is the rule that keeps the tool honest, and it is what makes the
demo land.

---

## 6. Priority and score

**Priority is calculated, not guessed:**

```
priority_score = (traffic_share × severity × confidence) ÷ effort
```

`traffic_share` = share of visitors who actually reach that section (1.0 when
`section_reach` was not supplied). `severity` 1–5 from the AI. `confidence` 0–1 from the
rule. `effort` 1–5, a rough estimate. Bucketed into High / Medium / Low only — three
buckets, because more would be noise.

**Health score** is six weighted categories rolled into one number, weights in
`config/scoring.php` and visible in the app:

| Category | Weight | Source |
|---|---|---|
| CTA effectiveness | 25% | AI vision + `cta_click_rate` |
| User experience | 20% | Bounce rate, section reach |
| UI design | 20% | AI vision, all sections |
| Trust | 15% | AI vision — proof, logos, badges |
| Performance | 10% | Playwright load timings |
| Accessibility | 10% | AI contrast and font-size judgment |

**Stated limitation:** the accessibility score is an AI approximation, not a real
axe-core audit. The report says so on the score breakdown. Honest labelling beats a
number that implies more rigour than it has.

---

## 7. Data model — ten tables become seven

| Table | Key columns |
|---|---|
| `pages` | `name`, `url`, `section_selectors` (json, nullable) |
| `audits` | `page_id`, `status`, `stage`, `overall_score`, `category_scores` (json), `token_cost`, `error_message` |
| `page_metrics` | `audit_id`, the seven form fields, `section_reach` (json), `source` |
| `screenshot_sections` | `audit_id`, `section_name`, `viewport`, `path`, `position`, `height` |
| `ai_findings` | `audit_id`, `screenshot_section_id`, `ai_score`, `problems` (json), `raw_response` (json), `model`, `tokens` |
| `insights` | `audit_id`, `section_name`, `rule_key`, `statement`, `evidence` (json), `confidence` |
| `recommendations` | `audit_id`, `insight_id`, `section_name`, `title`, `description`, `expected_impact`, `priority`, `priority_score`, `effort` |

Dropped: `users`, `projects`, `api_credentials`.

---

## 8. API — eleven endpoints become six

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/api/pages` | List, with the latest score on each |
| `POST` | `/api/pages` | Add a page. `422` with field errors if invalid |
| `POST` | `/api/pages/{id}/audits` | Start an audit; body carries the metrics. Returns `201 {id, status}` |
| `GET` | `/api/audits/{id}` | `{status, stage}` — polled every 5s |
| `GET` | `/api/audits/{id}/report` | The whole audit in one response (~40 KB) |
| `GET` | `/api/audits/{id}/pdf` | Server-rendered PDF via Browsershot |

Conventions carried over from `CLAUDE.md`:

- Controllers are thin: FormRequest → service → API Resource.
- **Never `return $model`.** A Resource is the only place that decides what React sees.
- Screenshots go out as temporary signed URLs, never raw disk paths.
- Every failure returns `{ message, errors? }` with a real HTTP status.
- `/report` stays one endpoint. Splitting it into ten tidy endpoints makes the
  dashboard visibly slower.

---

## 9. Screens — two

**Pages** (`/`) — list with a latest-score chip per page, an add-page form (name +
URL), and a "Run audit" button that opens the seven-field metrics form.

**Report** (`/audits/{id}`) — one scroll:

1. Health score dial + the three highest-priority fixes, right at the top. Someone who
   reads only this far still knows what to do.
2. Section cards: screenshot on the left, score and findings on the right. Seeing the
   picture next to the words is what makes the advice believable.
3. The full ranked list, grouped High / Medium / Low.
4. PDF export button.

Rules for both screens:

- No screen shows a blank white area: loading skeleton, empty state, and error state
  with a retry, every time.
- Every metric carries a one-line explanation of what it means.
- No internal vocabulary. Users manage *landing pages* and see *fixes*, not *entities*
  and *recommendation records*.

Recharts is cut — the only chart left is a score dial, which is ~20 lines of SVG.

---

## 10. Stack

Laravel 12 · PHP 8.3 · PostgreSQL 16 (JSON columns matter for AI responses and
section-reach maps) · Redis + queue worker · React 19 + Vite + Tailwind · React Router ·
Axios · TanStack Query · Playwright driven from PHP by `Symfony\Component\Process` via
`scripts/capture.mjs` · Browsershot for PDF (reuses the Chromium already installed) ·
local disk for screenshots.

Horizon is installed as a **development tool** for watching jobs succeed and fail during
the build week, not as a V1 feature.

No auth, no Sanctum. React and the API share one domain; a catch-all web route serves
React's `index.html` so refreshing on `/audits/42` does not 404.

---

## 11. Seven days

| Day | Ships | Done when |
|---|---|---|
| 1 | Laravel + React shell, pages CRUD, audit row queued | You add a page in React, see it listed, and clicking Run audit creates a `pending` audit |
| 2 | Playwright capture + section detection | After an audit you can open a gallery and see the page's sections, desktop and mobile |
| 3 | Vision call + JSON schema validation | Every section has a 0–100 score and at least one specific, non-obvious problem in plain language |
| 4 | Four rules + recommendations + score | Every insight on screen names a number and a visual cause. No insight is a generic design tip |
| 5 | Report screen | Someone who has never seen the tool names the top fix, unprompted, in under two minutes |
| 6 | PDF + run 4 real landing pages | A real page produces advice you would actually act on |
| 7 | Buffer + rehearsal | The demo runs end to end twice without you touching a terminal |

---

## 12. Testing

Scoped to what a week allows and to what actually protects the product.

| Kind | Target | Why |
|---|---|---|
| Unit | `SeenButNotClicked`, `DropOffBeforeSection`, `MobileGap`, `TrustGapEarly` | The rules *are* the product; each is a pure function |
| Unit | `CorrelationService::filterUnevidenced()` | Enforces the evidence guarantee |
| Unit | `PriorityScorer`, `HealthScorer` | Pure arithmetic, trivially testable, silently wrong if not |
| Unit | `AuditSchema` validation | Proves a malformed AI reply is rejected, not half-saved |
| Feature | `POST /api/pages/{id}/audits`, `GET /api/audits/{id}/report` | Endpoint contracts the React app depends on |
| E2E | Add page → run audit → read report | The demo path itself |

---

## 13. Known risks

| Risk | Mitigation |
|---|---|
| AI critique reads as generic and the demo falls flat | Day 6 is reserved for running real pages and reading output as a judge. Prompt tuning is the fix, and it needs a full day |
| Section detection produces meaningless bands on a real page | Three-level fallback; the optional CSS-selector field is the escape hatch |
| Playwright/Chromium memory or timeouts on demo day | Seeded example audit means the demo never depends on a live capture |
| Free-tier rate limits during the build week | Driver abstraction — flip `AI_DRIVER` to Claude |
| Accessibility score implies more rigour than it has | Labelled as an AI approximation in the report |
| Nothing is in version control | The repo is not a git repo. `git init` before day 1 |

---

## 14. Feature breakdown

Eleven features, each getting its own doc under `docs/features/`, and all eleven tracked
on one V1 dashboard.

| # | Feature | Slug |
|---|---|---|
| F00 | Setup and app shell | `f00-setup-and-shell` |
| F01 | Landing pages (add and list) | `f01-pages` |
| F02 | Metrics input form | `f02-metrics-input` |
| F03 | Audit pipeline and progress | `f03-audit-pipeline` |
| F04 | Screenshot capture | `f04-screenshot-capture` |
| F05 | AI vision analysis | `f05-ai-vision` |
| F06 | Correlation engine | `f06-correlation` |
| F07 | Recommendations and priority | `f07-recommendations` |
| F08 | Health score | `f08-health-score` |
| F09 | Report screen | `f09-report-screen` |
| F10 | PDF export | `f10-pdf-export` |

Build order: F00 → F01 → F02 → F03 → F04 → F05 → **F06** → F07 → F08 → F09 → F10.
F06 is the product. Everything before it is data collection; everything after it is
presentation.
