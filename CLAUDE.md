# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

**DropSense AI** — an AI conversion auditor. Paste a landing page URL, and it explains
*why* visitors leave and *how* to fix it, with every insight tied to a real number and a
real section of the page.

V1 is **built and working end to end**: capture → one AI vision call → four PHP
correlation rules → ranked recommendations → score → two React screens → PDF. The repo
is under git.

The current plan is the DropSense reduction, which adds three capabilities on top:

- **HTML crawl → AI rewrite** (F11) — read the page's real headline and button text, and
  rewrite them on click
- **Lighthouse** — replaces the two weakest inputs to the score with measurements
- **Demo GA4 + Clarity fixture** — removes typing from the demo, and feeds a fifth
  correlation rule

**Read before writing any code:**

1. `docs/superpowers/specs/2026-08-03-dropsense-ai-design.md` — the current design
2. `docs/features/README.md` — the twelve features and what changed in each
3. The task body on the board

`docs/superpowers/specs/2026-08-02-landing-page-auditor-v1-design.md` is the V1 spec. It
is still accurate for everything it describes; where the two disagree, the newer one wins.

`doc/` (singular) holds the original long-form plan,
`doc/ai-landing-page-ux-auditor-laravel-plan.html`. It is a historical source, not a
current instruction — it describes a 7-week, 13-AI-call product that was deliberately
reduced.

## The board is the source of truth for what to do next

Tasks live in `kanban/tasks/NNN-slug.md` with YAML frontmatter (`id`, `status`,
`priority`, `parent`, `depends_on`) and a body ending in that task's own DoD. Epics carry
the `epic` tag; everything else has a `parent`.

```bash
kanban-md board --compact                                  # where things stand
kanban-md list --compact --status todo                     # ready to start
kanban-md list --compact --tag dropsense                   # everything the new plan added
kanban-md list --compact --parent 123                      # the AI-rewrite epic
kanban-md show 141                                         # the DropSense Definition of Done
kanban-md list --compact --not-blocked --status backlog    # what would unlock next
kanban-md pick --claim <you> --status todo --move in-progress
```

Dependencies are recorded on the board, so build order is enforced rather than remembered.
`in-progress` and `review` require a claim; claims time out after 1h.

## Architecture

One repository, two applications. Laravel serves JSON at `/api/*` and React's
`index.html` for every other URL. No auth — React and the API share one domain.

**The audit pipeline** — one `Bus::chain()` of four stages, because each stage needs the
previous stage's rows and a failure must stop the rest rather than produce a half-audit
that looks complete:

```
CaptureScreenshotsJob → AnalyzePageJob → CorrelateJob → RankAndScoreJob
```

`CaptureScreenshotsJob` does everything one browser visit can do: screenshots, section
positions, the page's own words, and Lighthouse. Adding a second browser launch to
re-fetch a page that is already open is the mistake this design exists to avoid.

`POST /api/pages/{id}/audits` returns `201 {status: "pending"}` immediately; React polls
`GET /api/audits/{id}` every 5s for `{status, stage}`.

**One AI call per audit.** All section images, the mobile shot, the metrics and the
measured section positions go in a single message. The plan's 13-call shape cost ~4x for
no more insight. The rewrite (F11) is the one *additional* call, and it only fires when a
user clicks.

**Correlation is five PHP rules** (`app/Services/Correlation/Rules/`), not an AI opinion:
`SeenButNotClicked`, `DropOffBeforeSection`, `MobileGap`, `TrustGapEarly`,
`RageClickMismatch`. Rules first because you can unit-test a rule and cannot unit-test an
opinion.

**The evidence guarantee**: an insight without a metric, a number and a section name is
discarded, not shown. Enforced in one place — `CorrelationService::filterUnevidenced()` —
with its own test. This is the rule that keeps the tool honest.

**Priority is calculated, not guessed**: `(traffic share × severity × confidence) ÷ effort`,
bucketed into High/Medium/Low only. **Conversion Score** is six weighted categories rolled
into one number, weights in `config/scoring.php` and visible in the app: CTA 25%, UX 20%,
UI 20%, Trust 15%, Performance 10%, Accessibility 10%. Performance and Accessibility come
from Lighthouse; when Lighthouse is missing they fall back and are labelled *estimated*.

**`GET /api/audits/{id}/report` returns the whole audit in one response.** All of the
report screen reads from that single result. Do not split it into ten tidy endpoints; the
dashboard gets visibly slower.

**Folder mirroring**: `docs/features/f04-*` ↔ `resources/js/features/` ↔
`app/Services/Capture/`. Keep them in step.

## Naming

The product is **DropSense AI** and the number is the **Conversion Score** — in the UI,
the PDF, the README and the docs. **The code keeps its V1 names**: `HealthScorer` is
still `HealthScorer`, the `audits` table is still `audits`. This is deliberate, not an
oversight. Do not "fix" it.

One collision worth knowing: Lighthouse calls its individual checks *audits* too. In this
codebase an audit is always a row in the `audits` table; Lighthouse's are *checks*.

## Stack (decided — don't re-litigate)

Laravel 12 · PHP 8.3 · **SQLite** · **database queue driver** · React 19 + Vite +
Tailwind · React Router · Axios · TanStack Query · Playwright driven from PHP by
`Symfony\Component\Process` via `scripts/capture.mjs` · Lighthouse over CDP against that
same browser · local disk for screenshots · Browsershot for PDF.

No Sanctum, no Postgres, no Redis, no Horizon, no S3, no Recharts. The V1 spec's stack
section names some of these; the running application does not use them, and a hackathon
week is not the time to add them. The only chart is a score dial, which is ~20 lines of SVG.

The AI call sits behind a `VisionAnalyzer` interface with three drivers — `stub`,
`gemini`, `claude` — selected by `AI_DRIVER` in `.env`. `stub` is the default so tests and
local work cost nothing.

Tooling: Pint + PHPStan for PHP, `tsc` + ESLint for React, Pest + Vitest + Playwright for
tests. The DoD bar requires `php artisan test`, `npm run type-check` and `npm test` to all
pass.

## Conventions

**Backend**
- Controllers are thin: FormRequest → service → API Resource.
- **Never `return $model`.** A Resource is the only place that decides what React sees, so
  adding a column can never leak it into the API by accident.
- Screenshots go out as temporary signed URLs, never raw disk paths.
- Every failure returns `{ message, errors? }` with a real HTTP status.
- Services are constructor-injected and unit-testable without the network.
- Every AI reply is schema-validated before anything is saved. A malformed reply is
  rejected, not half-saved.

**Frontend**
- One barrel-exported folder per feature under `resources/js/features/`; `pages/*.jsx` are
  thin route wrappers with no logic.
- Data fetching only through TanStack Query hooks.
- One Axios interceptor turns any error into a toast; no component handles HTTP errors
  itself.

**Both**
- No screen may show a blank white area: loading skeleton, empty state, and error state
  with a retry, every time.
- Every metric shown to a user carries a one-line explanation of what it means.
- No internal vocabulary in the UI — users manage *landing pages* and see *fixes*, not
  *entities* and *recommendation records*. Buttons say what happens ("Run audit", not
  "Submit") and keep the same name through the whole flow.
- Anything shown from the demo fixture is **labelled as demo data**, not passed off as the
  user's own numbers.

## Closing a task

Feature-specific DoD true → `review`. The shared bar in `doc/02-definition-of-done.md`
also true → `done`. That shared bar includes: you ran the whole flow yourself in a browser
on a real landing page, once at 390 px wide; the unhappy path is handled and no audit can
be stuck on `running` forever; the doc in `docs/features/` matches what you actually built
**in the same commit**; and any decision the doc did not already answer is recorded in the
kanban task body.
