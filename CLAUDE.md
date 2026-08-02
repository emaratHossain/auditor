# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository currently is

**Specification and planning only — there is no application code yet.** The repo holds two things:

- `doc/` — the product spec, architecture and shared Definition of Done
- `kanban/` — a [kanban-md](https://github.com/) board with 66 tasks across 11 epics

It is also **not a git repository**. The first real code task is `#12` (scaffold Laravel 12) and
`#13` (scaffold React 19), which create the application described in `doc/01-architecture.md`
around this documentation.

Known gaps: `doc/README.md` links to `doc/features/F00…F10.md`, `doc/03-ui-flows.md` and
`doc/ui-mockups.html`, none of which exist yet. `doc/features/` is an empty directory. Do not
assume a feature file is there — check first, and prefer
`doc/ai-landing-page-ux-auditor-laravel-plan.html` (the original long-form plan, the most detailed
source available) plus the task body on the board.

## The board is the source of truth for what to do next

Tasks live in `kanban/tasks/NNN-slug.md`, each with YAML frontmatter (`id`, `status`, `priority`,
`parent`, `depends_on`) and a one-paragraph body that ends with that task's own DoD. Epics are
tasks `#1`–`#11` and carry the `epic` tag; every other task has a `parent`.

```bash
kanban-md board --compact                                  # where things stand
kanban-md list --compact --status todo                     # ready to start (currently 6)
kanban-md list --compact --parent 7                        # every task in the correlation epic
kanban-md show 47                                          # one task in full
kanban-md list --compact --not-blocked --status backlog    # what would unlock next
kanban-md pick --claim <you> --status todo --move in-progress
```

Dependencies are recorded on the board, so build order is enforced rather than remembered.
`in-progress` and `review` require a claim; claims time out after 1h.

## Build order

`F00 setup → F01 auth → F02 pages → F03 orchestration → (F04 analytics ∥ F05 screenshots) →
F06 AI vision → F07 correlation → F08 recommendations + score → F09 dashboard → F10 reports`

F04 and F05 are independent and parallelisable. **F07 is the product** — everything before it is
data collection, everything after is presentation.

## Architecture the code must be built into

One repository, two applications. Laravel serves JSON at `/api/*` and React's `index.html` for
every other URL. Read `doc/01-architecture.md` before writing any code; the essentials:

**The audit pipeline** — one `Bus::chain()` of seven stages, because each stage needs the previous
stage's rows and a failure must stop the rest rather than produce a half-audit that looks complete:

```
FetchAnalyticsJob → CaptureScreenshotsJob → AnalyzeSectionJob ×6 (Bus::batch inside the chain)
  → CorrelateJob → RecommendJob → ScoreJob → NotifyJob
```

`POST /api/pages/{id}/audits` returns `201 {status: "pending"}` immediately; React polls
`GET /api/audits/{id}` every 5s for `{status, stage}`. Nobody waits on a spinner.

**Correlation runs in two passes** (`app/Services/Correlation/`): four cheap, testable PHP rules
first (`SeenButNotClicked`, `DropOffBeforeSection`, `RageClickMismatch`, `MobileGap`), then one
whole-page AI text call over all findings and metrics for the cross-section story rules cannot
see. Rules first because you can unit-test a rule and cannot unit-test an opinion.

**The evidence guarantee**: an insight without a metric, a number and a section name is discarded,
not shown. This is the rule that keeps the tool honest — task `#52` exists solely to enforce it.

**Priority is calculated, not guessed**: `(traffic share × severity × confidence) ÷ effort`,
bucketed into High/Medium/Low only. **Health score** is six weighted categories rolled into one
number, weights in `config/scoring.php` and visible in the app: CTA 25%, UX 20%, UI 20%, Trust
15%, Performance 10%, Accessibility 10%.

**The React ↔ Laravel seam** is the endpoint list in `doc/01-architecture.md` /
the plan HTML. `GET /api/audits/{id}/report` returns the whole audit (~60 KB) in one response —
all four dashboard screens read from that single cached result. Do not split it into ten tidy
endpoints; the dashboard gets visibly slower.

**Folder mirroring**: `doc/features/F04-*` ↔ `resources/js/features/analytics/` ↔
`app/Services/Analytics/`. Keep the three in step.

## Stack (decided — don't re-litigate)

Laravel 12 · PHP 8.3 · PostgreSQL 16 · Redis + Horizon · Sanctum in **cookie mode** (React and
the API share one domain) · React 19 + Vite + Tailwind · React Router · Axios · TanStack Query ·
Recharts · Playwright driven from PHP by `Symfony\Component\Process` via `scripts/capture.mjs` ·
Claude Sonnet 5 per section, Opus 5 for the whole-page correlation pass · S3 (local disk in dev,
90-day lifecycle) · Browsershot for PDF.

Tooling per task `#16`: Pint + PHPStan for PHP, `tsc` + ESLint for React, Pest + Vitest for tests.
Once scaffolded, the DoD bar in `doc/02-definition-of-done.md` requires `php artisan test`,
`npm run type-check` and `npm test` to all pass.

## Conventions

**Backend**
- Controllers are thin: FormRequest → service → API Resource.
- **Never `return $model`.** A Resource is the only place that decides what React sees, so adding
  a column can never leak it into the API by accident.
- Screenshots go out as temporary signed URLs, never raw S3 paths.
- Every failure returns `{ message, errors? }` with a real HTTP status.
- Services are constructor-injected and unit-testable without the network.
- Every query is scoped to the signed-in user; a feature test must prove user B gets 403 on user
  A's data.

**Frontend**
- One barrel-exported folder per feature under `resources/js/features/`; `pages/*.tsx` are thin
  route wrappers with no logic.
- Data fetching only through TanStack Query hooks in `features/<x>/hooks/api/`.
- One Axios interceptor turns any error into a toast; no component handles HTTP errors itself.
- Zod mirrors validation for instant feedback, but the server's 422 body always wins.

**Both**
- No screen may show a blank white area: loading skeleton, empty state, and error state with a
  retry, every time.
- Every metric shown to a user carries a one-line explanation of what it means.
- No internal vocabulary in the UI — users manage *landing pages* and see *fixes*, not *entities*
  and *recommendation records*. Buttons say what happens ("Run audit", not "Submit") and keep the
  same name through the whole flow.

## Closing a task

Feature-specific DoD true → `review`. The shared bar in `doc/02-definition-of-done.md` also true →
`done`. That shared bar includes: you ran the whole flow yourself in a browser on a real landing
page, once at 390 px wide; the unhappy path is handled and no audit can be stuck on `running`
forever; the doc in `doc/features/` matches what you actually built **in the same commit**; and
any decision the doc did not already answer is recorded in the kanban task body.
