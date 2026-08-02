# Feature docs — Landing Page Auditor V1

**Start here:** open [`v1.dashboard.html`](v1.dashboard.html) in a browser. It is the
one page to keep open all week — the flow, the Definition of Done, and every card on the
board, grouped by the day you need it.

The full design decisions live in
[`../superpowers/specs/2026-08-02-landing-page-auditor-v1-design.md`](../superpowers/specs/2026-08-02-landing-page-auditor-v1-design.md).

## The eleven features, in build order

| # | Feature | Doc | Cards |
|---|---|---|---|
| F00 | Setup and app shell | [`f00-setup-and-shell.md`](f00-setup-and-shell.md) | `kanban-md list --compact --tag f00-setup` |
| F01 | Landing pages | [`f01-pages.md`](f01-pages.md) | `--tag f01-pages` |
| F02 | Metrics input form | [`f02-metrics-input.md`](f02-metrics-input.md) | `--tag f02-metrics` |
| F03 | Audit pipeline and progress | [`f03-audit-pipeline.md`](f03-audit-pipeline.md) | `--tag f03-pipeline` |
| F04 | Screenshot capture | [`f04-screenshot-capture.md`](f04-screenshot-capture.md) | `--tag f04-capture` |
| F05 | AI vision analysis | [`f05-ai-vision.md`](f05-ai-vision.md) | `--tag f05-ai-vision` |
| **F06** | **Correlation engine** | [`f06-correlation.md`](f06-correlation.md) | `--tag f06-correlation` |
| F07 | Recommendations and priority | [`f07-recommendations.md`](f07-recommendations.md) | `--tag f07-recommendations` |
| F08 | Health score | [`f08-health-score.md`](f08-health-score.md) | `--tag f08-score` |
| F09 | Report screen | [`f09-report-screen.md`](f09-report-screen.md) | `--tag f09-report` |
| F10 | PDF export | [`f10-pdf-export.md`](f10-pdf-export.md) | `--tag f10-pdf` |

**F06 is the product.** Everything before it is data collection; everything after it is
presentation.

## Useful commands

```bash
kanban-md board --compact                              # where things stand
kanban-md list --compact --not-blocked                 # what you can start right now
kanban-md list --compact --tag tech-debt               # what you are carrying knowingly
kanban-md list --compact --tag v2                      # what was cut, and why
kanban-md show 122                                     # the whole V1 Definition of Done
kanban-md pick --claim you --status todo --move in-progress
```

## Keeping this honest

The docs and the board are the durable record. The dashboard is a **regenerated view of
them** — it never updates itself, so re-generate it (overwrite the same file) whenever
the code or the board moves. That is what stops a good-looking document quietly going
stale and becoming untrustworthy.

The dashboard's tick boxes live only in your browser's local storage, which is per-machine
and easy to lose. The Definition of Done durably lives on card `#122`.

**Note on version control:** `docs/` and `kanban/` are local records. The repository is
not under git yet — card `#78` covers fixing that, and it should be the first thing you do.
