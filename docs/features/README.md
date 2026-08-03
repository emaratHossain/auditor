# Feature docs — DropSense AI

**An AI conversion auditor that combines page analysis with analytics to explain *why*
visitors leave and *how* to fix it.**

The current design decisions live in
[`../superpowers/specs/2026-08-03-dropsense-ai-design.md`](../superpowers/specs/2026-08-03-dropsense-ai-design.md).
It supersedes the
[V1 spec](../superpowers/specs/2026-08-02-landing-page-auditor-v1-design.md), which is
still accurate for everything it describes — the V1 pipeline is not being rebuilt.

## Where things stand

V1 shipped end to end: capture → one vision call → four correlation rules → ranked
recommendations → score → two screens → PDF. **DropSense is that, plus three
capabilities and a face:**

| Added | Why it earns its day |
|---|---|
| **HTML crawl → AI rewrite** (`F11`) | The *Fix* in Detect → Explain → Fix. Everything else tells you what is wrong; this is the only part that hands you something to paste |
| **Lighthouse** (`F04`, `F08`) | Turns the two weakest numbers in the score — speed and accessibility — from guesses into measurements, and lets a printed caveat be deleted honestly |
| **Demo GA4 + Clarity fixture** (`F02`) | Removes typing from the demo, and revives the fifth correlation rule that V1 had no data for |
| **A design pass on the report screen** (`F09`) | *Beautiful dashboard* is one of the five stated winning points |

**Cut deliberately:** the JavaScript tracker. The plan itself marks it mock-for-now, and
a mock tracker produces no data a judge can see on stage. It is card `#142`.

## The twelve features, in build order

| # | Feature | Doc | DropSense change | Cards |
|---|---|---|---|---|
| F00 | Setup and app shell | [`f00-setup-and-shell.md`](f00-setup-and-shell.md) | rename | `--tag f00-setup` |
| F01 | Landing pages | [`f01-pages.md`](f01-pages.md) | — | `--tag f01-pages` |
| F02 | Metrics input | [`f02-metrics-input.md`](f02-metrics-input.md) | fixture + pre-fill | `--tag f02-metrics` |
| F03 | Audit pipeline | [`f03-audit-pipeline.md`](f03-audit-pipeline.md) | — still four stages | `--tag f03-pipeline` |
| F04 | Capture | [`f04-screenshot-capture.md`](f04-screenshot-capture.md) | **+ crawl, + Lighthouse** | `--tag f04-capture` |
| F05 | AI vision | [`f05-ai-vision.md`](f05-ai-vision.md) | — | `--tag f05-ai-vision` |
| **F06** | **Correlation engine** | [`f06-correlation.md`](f06-correlation.md) | **+ fifth rule** | `--tag f06-correlation` |
| F07 | Recommendations | [`f07-recommendations.md`](f07-recommendations.md) | — | `--tag f07-recommendations` |
| F08 | Conversion Score | [`f08-health-score.md`](f08-health-score.md) | **two categories measured** | `--tag f08-score` |
| F09 | Report screen | [`f09-report-screen.md`](f09-report-screen.md) | **design pass + rewrite panel** | `--tag f09-report` |
| F10 | PDF export | [`f10-pdf-export.md`](f10-pdf-export.md) | carries rewrites | `--tag f10-pdf` |
| **F11** | **AI copy rewrite** | [`f11-ai-rewrite.md`](f11-ai-rewrite.md) | **new** | `--tag f11-rewrite` |

**F06 is still the product.** Everything before it is data collection; everything after
it is presentation. F11 is what turns the presentation into an action.

## The week

| Day | Ships |
|---|---|
| 1 (Aug 3) | Rebrand, demo fixture, pre-filled form — `#124` `#125` `#126` |
| 2 (Aug 4) | HTML crawl, stored per section — `#127` `#128` |
| 3 (Aug 5) | Rewrite table, service, endpoint, panel — `#130` `#131` `#132` |
| 4 (Aug 6) | Lighthouse and the score rewiring — `#129` `#133` |
| 5 (Aug 7) | Fifth rule and the four new tests — `#134` `#135` `#136` `#137` `#138` |
| 6 (Aug 8) | Design pass on the report screen — `#139` |
| 7 (Aug 9) | Seed, four real pages, rehearse twice — `#140` `#141` |

## Useful commands

```bash
kanban-md board --compact                              # where things stand
kanban-md list --compact --tag dropsense               # everything this plan added
kanban-md list --compact --not-blocked                 # what you can start right now
kanban-md list --compact --tag tech-debt               # what you are carrying knowingly
kanban-md list --compact --tag v2                      # what was cut, and why
kanban-md show 141                                     # the DropSense Definition of Done
kanban-md pick --claim you --status todo --move in-progress
```

## Keeping this honest

The docs and the board are the durable record. `v1.dashboard.html` is a **regenerated
view of them** and never updates itself — it currently reflects the V1 board, not this
plan. Re-generate it, or ignore it and read the board.

Its tick boxes live in browser local storage, which is per-machine and easy to lose. The
durable Definition of Done is card `#141`.
