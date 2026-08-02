---
name: visual-feature-tracking
description: Use when the user says "plan for a new feature" / "plan this update", "fix this issue", or "explain this feature" — and whenever they want to map, document, track, or "keep control of" any part of this Eventin plugin — its flow, what makes it successful, what's pending, and what's owed as tech debt. Also triggers on "I keep losing track of the code", "what's the flow / what's left / what's the tech debt", "create a feature dashboard", "map this for me", or right after implementing a feature or landing a fix. Prefer this over writing prose docs — the maintainer fatigues on walls of text and wants visual, scannable tracking backed by real kanban cards.
---

# Visual Feature Tracking (Eventin)

The maintainer loses the sense of control over the codebase when a lot of code gets generated fast, and gets fatigued reading dense, over-informative docs. This skill answers four questions for any feature — **(1) how it flows, (2) what makes it successful, (3) what's pending, (4) what's owed as tech debt** — using diagrams, screenshots, checklists, and a task board instead of prose.

## Step 0 — Pick the mode from what the user asked for

Three asks, three required output sets. **Everything in the "Must produce" column is required — a run that ends with a pretty document but an untouched board is not finished.**

| The user says | Enter through | Must produce |
|---|---|---|
| **"plan for a new feature" / "plan this update"** — nothing built yet | `superpowers:brainstorming` — agree scope + design first | ① plan doc `docs/features/<slug>.md` ② visual dashboard `<slug>.dashboard.html` ③ Mermaid flow of the agreed design ④ kanban build-task cards ⑤ kanban test cards: unit + feature **and** e2e |
| **"fix this issue"** | `superpowers:systematic-debugging` — prove the root cause first | ① visual dashboard with **screenshots** + a `CAUSE` block saying what you see / why it happens / what to do ② `docs/features/<slug>.md` mirror ③ kanban fix cards ④ kanban test cards: unit + feature **and** e2e (at least one is the regression test) |
| **"explain this feature"** | neither — read the real code (dispatch `Explore`) | ① visual dashboard with **screenshots**, each labelled with what to look at ② the true end-to-end flow with `file:line` ③ **what's undone**: unfinished work + missing test coverage, as real kanban cards, not prose |

Announce the entry point ("Using superpowers:brainstorming to agree the scope before I diagram it"), run it to its conclusion, then come back here.

**Do not draw the diagram from your own idea of what the feature should be.** For a plan the flow must depict the design the user agreed to in brainstorming — a diagram of an unapproved plan looks authoritative and is worse than no diagram. Same rule for a fix: **no fix card, no debt card, no `CAUSE` block, no diagram annotation until the root cause is proven.** A card built on a guessed cause sends a developer down the wrong path with full confidence.

If brainstorming or debugging is genuinely already done earlier in this conversation, say so in one line and continue — but "I can tell what they want" is not the same as having done it.

## The one principle that shapes everything

**The `.md` doc and the kanban board are the durable record; the dashboard is a regenerated view of them.** The dashboard — a self-contained `docs/features/<slug>.dashboard.html` the user opens straight in a browser — is a beautiful *snapshot* that never auto-updates, so treat it as disposable and re-generate it (overwrite the same file) whenever the code or board moves. This is what keeps a nice-looking doc from quietly going stale and becoming untrustworthy — the exact failure mode the maintainer is worried about.

**Note on what's versioned:** in this repo **both `docs/` and `kanban` are gitignored** (`.gitignore:12`, `.gitignore:19`). So everything this skill writes is a *local* record — durable across sessions on this machine, but not shared with the team and not in the release zip. Keep the doc's tables (tests, gaps-and-debt) in sync with the board so a future session can rebuild the picture from the doc alone. (If the user wants any of it shared, that's a `.gitignore` change to raise with them — don't assume it.)

Corollary: **visual first, minimal prose.** Reach for a Mermaid diagram, a screenshot, a checkbox, or a short table before a paragraph. Write sentences only where a picture genuinely can't carry the meaning.

## Write for a tired non-expert (plain-English rule)

Everything this skill produces — the dashboard, the doc, and the kanban cards — must read in the **simplest English possible**. Assume the reader is tired, skimming, and does not know the code. Three concrete rules:

1. **Self-explaining flow names.** Each flow step's name must say *what happens*, not name the class or layer. Write "A customer buys a ticket" / "We check the coupon" / "We email the ticket" — **not** "REST in", "Order service", "Persist". A person who has never seen the code should understand the step from its name alone. Keep the `file:line` — just move it out of the name and into the detail.
2. **The click explains it in one plain sentence.** When a reader clicks a step (dashboard) the first line they see is a short, jargon-free sentence about *what is happening and why it matters to them* — e.g. "The email goes out in the background so the buyer isn't kept waiting." The technical `file:line` follows underneath, not instead.
3. **Prefer the everyday word.** "reply" over "outbound message", "discount" over "coupon adjustment line", "make sure it's genuine" over "authenticate". Keep exact identifiers (`total_price`, `payment_method === 'wc'`) where precision matters, but wrap them in a plain sentence so the meaning survives even if the reader skips the identifier.

Litmus test: read any step name, screenshot caption, or card title aloud to someone non-technical — if they can't guess what it means, rewrite it.

## Workflow

### 1. Understand the feature from the real code

Trace it end to end — configure → store → use — from the actual source, not a mental model. Cite real `file:line` references; a diagram built on guesses is worse than none. For anything beyond a couple of files, dispatch an `Explore` subagent and ask it for: the end-to-end flow with file:line refs, key files, existing tests, test gaps, likely tech debt (TODOs, hardcoded values, N+1s, **committed secrets**), and anything unfinished. Surface risks as you find them — a leaked secret or auth hole becomes a `critical` card immediately, not a footnote.

### 2. Capture the screenshots (fix + explain modes: required)

A "visual doc with proper screenshots" means real screens from the running site, not stock diagrams.

- **Where to shoot:** the local site — base URL and logins come from `$EVENTIN_QA_BASE_URL` / `$EVENTIN_QA_*_USER|PASS` in `.claude/settings.local.json`. Drive it with the Playwright MCP tools, or reuse `tests/e2e/helpers.js` + the stored session in `tests/e2e/.auth/`.
- **Where to save:** `docs/features/evidence/<slug>-<what>.png` — e.g. `refund-coupon-total-before.png`. Lowercase, hyphenated, one screen per file.
- **What to shoot, per mode:**
  - *Fix* — the wrong output (`kind:"bad"`) and, once fixed, the same screen right (`kind:"good"`). Same page, same data, so the difference is obvious.
  - *Explain* — one shot per meaningful screen in the flow (`kind:"info"`): where the setting lives, what the buyer sees, what lands in the admin list.
- **Annotate in words, not pixels.** Don't try to draw arrows. Each `SHOTS` entry gets a `t` naming the screen and a `d` that is one plain sentence saying *what to look at* — "The total still says $50 even though the 10% coupon was applied." That's the "identification" the user is asking for, and it stays readable and editable.
- **Never ship a broken image.** The dashboard prints `screenshot not found: <path>` in place of a missing file — if you see that, the path is wrong. Paths are relative to the dashboard, so `evidence/x.png`, never an absolute path.

### 3. Write the doc

Create `docs/features/<feature-slug>.md` from `references/feature-doc-template.md`. It holds:
- a one-line "what this is" and a link to the dashboard,
- **fix mode:** the what-you-see / why-it-happens / what-to-do block (proven cause only),
- a ` ```mermaid ` flow diagram — in fix mode, colour the step that breaks with the `broken` classDef,
- the screenshots table, so the doc stands alone without the dashboard,
- a **Definition of Done** checklist (the test areas / "what makes it successful"), each item with a concrete example and — for anything not yet true of the code — the id of its card from step 4,
- a **Tests** table listing the unit/feature and e2e cards with their target files,
- a short gaps-and-debt table that mirrors the kanban cards.

For a plan the DoD lines come from what the user agreed to in brainstorming. For a fix, add the regression criterion that would have caught it. For an explain run, the DoD is what *would* make it successful — every line that isn't true today is undone work and needs a card.

Validate the Mermaid renders before committing to it — a broken diagram helps no one. Use the render script from the `mermaid-diagram` skill, which is usually **user-scoped**, so locate it rather than hardcoding a project path:
`SCRIPT=$(ls ~/.claude/skills/mermaid-diagram/references/render_mermaid.sh .claude/skills/mermaid-diagram/references/render_mermaid.sh 2>/dev/null | head -1)` then `bash "$SCRIPT" <tmp>.mmd <tmp>.png`. View the PNG, fix any syntax, then embed the validated block. Write the temp file with a feature-slug prefix (e.g. `<slug>-flow.mmd`) in the scratchpad so parallel runs don't collide. Keep node labels short and put `file:line` refs in `<i>…</i>`.

### 4. Put the work on the board

Use the `kanban-md` CLI (this project's board lives in `kanban/tasks/`). Check what's there first — `kanban-md list --compact` — and enrich a matching card rather than duplicating it.

**Four groups of cards are required every run. A run that produces a diagram but no DoD cards and no test cards is not finished.**

**a. The DoD home card.** One **"QA verify: `<feature>`"** card whose body is the Definition of Done as a Markdown checklist (`kanban-md edit <id> --append-body "…"`). Tag `QA` + feature area. Note its id and filename — the dashboard links every DoD line to it. The dashboard's ticks live only in browser `localStorage` (per-machine, easily lost), so this card is where the DoD durably lives; nothing may exist only in the browser.

**b. One card per DoD criterion that isn't met yet.** Read each DoD line and ask "is this true of the code today?" Every "no" becomes its own actionable card — tagged `pending` + feature area — and its id goes next to that line in the doc. Criteria already satisfied by shipped code stay checkboxes only. This is what makes the DoD *work to be done* rather than a wish list.

**c. Test cards — unit/feature and e2e, written separately.** Every DoD criterion must be reachable from at least one test card.
- **Unit / feature** — PHPUnit, in `tests/phpunit/tests/<Thing>Test.php`, run with `npm run test:unit`. One card per class/service whose logic can be exercised without a browser (calculations, refunds, permissions, REST responses). Tag `QA`, `test-gap`, `test-unit`. Title it `Test: <thing>`. The body lists the cases as plain sentences ("a 10% coupon is subtracted once, not twice"), not as assertion shorthand.
- **E2E** — Playwright, in `tests/e2e/<feature>.spec.js`, run with `npm run test:e2e`. One card per user journey through the flow diagram: an admin screen, a checkout, a front-end display. Tag `QA`, `test-gap`, `test-e2e`. The body reads *what the user does → what they should see on screen*.
- Split them even when they cover the same behaviour. They fail for different reasons, run at different speeds, and get picked up at different times; one merged "add tests" card always gets half-done.
- Name the target file in the card so whoever picks it up doesn't have to guess, and point at `tests/HOW-TO-RUN.md` for setup.

**d. Pending build work + tech debt.** Pending/to-build → feature area + `pending`. Tech debt → `tech-debt`; security issues get `--priority critical` and a `security` tag. Anything cut, hacked, hardcoded, or left unfinished during the work goes here in the same change — including anything brainstorming explicitly deferred out of scope. **In explain mode this group is the answer to "what's undone"** — if you found unfinished work and it only appears as prose in the doc, you skipped this step.

Before moving on, run `kanban-md list --compact --tag <area>` and confirm every DoD line traces to a card id. If a line has none, you missed one.

**Write cards for a human, not for an AI agent.** The body must read like a task a developer can pick up cold — not a terse spec-assertion or a string of `symbol → 422 (File.php:12)` shorthand. That shorthand is unreadable to a person: it says *what a test asserts*, not *what's wrong and what to do*. Give every card this shape, in plain English:

- **What's wrong / what's missing** — one or two plain sentences a non-author understands. ("The coupon discount is subtracted a second time when the order is displayed, so the buyer sees a total lower than what they paid.")
- **What to do** — the concrete action(s) to take, as steps or a short list. This is the part the shorthand always omits.
- **Where** — the `file:line` pointers, at the end, as *pointers* to the code — never as the whole message.

Keep exact identifiers (`total_price`, `payment_method`, HTTP `422`) where they add precision, but always inside a plain sentence so the meaning survives if the reader skips the identifier. Litmus test: a developer who has never seen this code should know what to do from the card alone.

### 5. Build the dashboard as a self-contained HTML file

**Do NOT publish to the Artifact service.** Write the dashboard as a standalone `.html` file the user opens directly in a browser — `docs/features/<slug>.dashboard.html`, right next to the doc. It works offline, needs no server, and has no external URL to lose.

Copy `assets/dashboard-template.html` to `docs/features/<slug>.dashboard.html`, then fill in:
- **config** — `REPO_ABS` = the repo's `pwd`; `DOD_TASK` = `{id, file}` of the QA card from step 4.
- **`CAUSE`** — fix mode only: `{sym, why, fix:[…]}`. Leave it `null` for plan and explain runs and the section disappears (section numbers re-stamp themselves).
- **`STEPS`** — the flow. Each entry needs a self-explaining `t` (the visible name, e.g. "We email the ticket"), a plain `who` (e.g. "the buyer"), and an `h` that is one jargon-free sentence; the `file:line` lives only in `d`/`f`, never in the name. Use `note` to hang a caveat or linked debt off a step.
- **`SHOTS`** — the screenshots from step 2, `src` relative (`evidence/<slug>-*.png`), `kind` = `bad` | `good` | `info`. Leave `[]` for a plan run with nothing to photograph.
- **`DOD`** — criteria with a concrete `eg`, plus `tests` where test methods exist.
- **`TRACK`** — the kanban cards, grouped. Give each item a repo-relative `file` (`kanban/tasks/NNN-*.md`) so it renders as a `vscode://` chip that opens the task in the user's editor.

Apply the plain-English rule to every `t` in `STEPS`, `SHOTS`, `DOD`, and `TRACK`. The template is self-contained (inline CSS/JS, light+dark themes, clickable flow, DoD ticks persisted in `localStorage`, priority-striped tracker) — you only edit data, not structure. It is a **complete standalone HTML document** (its own `<!doctype>`/`<head>`/`<body>`, because there's no Artifact tool to inject them) — don't strip those tags. The masthead's corner "stamp" (`::after { content: "STAMP" }`) is decorative — give it a 4–8 char label or leave it. Skip loading `artifact-design`; this template already is the design system.

**Surface it to the user:** after writing the file, use `SendUserFile` with `display: "render"` so the dashboard opens inline for them, and mention the on-disk path so they know where it lives. To view it again later they just open that file.

### 6. Refresh, don't recreate

When the feature changes later, update the doc + board first, then re-generate the dashboard by **overwriting the same `docs/features/<slug>.dashboard.html`** (re-fill the data). The path is stable, so the user's open tab keeps pointing at the right file.

## What "good" looks like

- A one-screen diagram a tired person can grasp in seconds — not a spec.
- The diagram shows the design the user agreed to (plan), the proven cause (fix), or the behaviour the code really has (explain) — never your own guess.
- Real screenshots, each with one plain sentence saying what to look at.
- A checklist where each line is objectively checkable, with a real example (`10% coupon → total drops once`, not "discounts work"), and each unmet line carries a card id.
- Unit/feature and e2e test cards exist as separate, pickup-ready tasks — not one vague "add tests".
- Every known shortcut is a card, so nothing rots silently in someone's head.
- The dashboard and the on-disk files agree, because the dashboard was generated from them.

## Red flags — stop and back up

- About to plan a feature that hasn't been brainstormed → run `superpowers:brainstorming` first.
- About to write a `CAUSE` block, fix card, or debt card for a bug whose cause you haven't proven → run `superpowers:systematic-debugging` first.
- "The design is obvious, I'll skip the brainstorm" → the point is the user's agreement, not your certainty.
- Fix or explain run with no screenshots → step 2 was skipped; the user asked for a *visual* doc.
- The dashboard shows `screenshot not found` → wrong path; fix it before surfacing the file.
- Diagram and dashboard done, board untouched → the run isn't finished; groups a–d in step 4 are all required.
- A DoD line with no card id next to it → step 4b was skipped.
- One card titled "add tests for `<feature>`" → split it into unit/feature and e2e cards.
- Explain run whose "what's undone" lives only in the doc's prose → it needs real cards.

## Files

- `references/feature-doc-template.md` — skeleton for `docs/features/<slug>.md` (cause block + mermaid + screens + DoD + tests + debt tables).
- `assets/dashboard-template.html` — the interactive dashboard (a complete standalone HTML document); copy to `docs/features/<slug>.dashboard.html`, fill `CAUSE` / `STEPS` / `SHOTS` / `DOD` / `TRACK` + masthead, and open it in a browser. Not published anywhere.
