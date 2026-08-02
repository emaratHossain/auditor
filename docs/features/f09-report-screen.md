# F09 — Report screen

**One line:** The one screen the demo lives on: the score, the three fixes that matter most, the section pictures next to what is wrong with them, then the full ranked list.

- **Mode:** plan · **Status:** planned — nothing built yet · **Epic:** `#76`
- **Visual doc:** [`v1.dashboard.html`](v1.dashboard.html) — open it in a browser
- **Tasks:** `kanban-md list --compact --tag f09-report`

## Flow

```mermaid
flowchart LR
    A["The browser asks for the finished audit<br/><i>Report.jsx</i>"] --> B["We assemble the whole report in one answer<br/><i>AuditReportResource.php</i>"]
    B --> C["We hand back signed picture links that expire"]
    C --> D["The screen draws the score and the top three fixes"]
    D --> E["Then the sections, picture beside findings"]
    E --> F["Then the full ranked list"]
    classDef fetch fill:#f4e6cc,stroke:#b5771f,color:#5a3d0d;
    classDef draw fill:#d7ebe8,stroke:#0f766e,color:#0f3d38;
    class A,B,C fetch;
    class D,E,F draw;
```

**One request, not ten.** The whole report comes back in a single answer of roughly
40 KB. Splitting it into ten tidy endpoints feels cleaner and makes the screen visibly
slower.

## What the screen shows, in this order

1. **The health score, with the three highest-priority fixes right underneath it.**
   Someone who reads only this far still knows what to do.
2. **Section cards** — the picture on the left, the score and findings on the right.
   Seeing the picture next to the words is what makes the advice believable.
3. **The full ranked list**, grouped High / Medium / Low.
4. **The download button.**

## Two rules for this screen

- **No number appears without a sentence saying what it means.**
- **Every number is clickable and leads to the finding that explains it.**

A screen that only shows numbers is the exact problem this product was built to solve.

**And no internal vocabulary.** Users manage *landing pages* and see *fixes* — not
*entities* and *recommendation records*. Buttons say what happens (*Run audit*, not
*Submit*) and keep the same name through the whole flow.

## What "done" means

- [ ] **Someone untrained gets it in two minutes** — show it to a person who has never seen the tool; they correctly name the top fix, unprompted · `#102`
- [ ] **The picture sits beside the words** — each section card shows its screenshot next to its findings, not on a separate tab · `#102`
- [ ] **Every number is explained** — no metric appears without a one-line note saying what it means · `#102`
- [ ] **Numbers lead somewhere** — clicking a number takes you to the finding that explains it · `#102`
- [ ] **No blank white area** — loading skeleton, empty state and error state with a retry all render · `#103`
- [ ] **It works on a phone** — check all three states once at 390px wide · `#103`
- [ ] **One request** — the screen makes a single call for the report, not one per panel · `#101`
- [ ] **The demo cannot fail on the network** — the seeded example audit opens and renders identically to a live one · `#105`

## Tests

| # | Kind | What it proves | Target file |
|---|---|---|---|
| `#110` | feature | The report endpoint returns score, sections, findings and recommendations in one response, with signed picture links; 404 for an unknown id | `tests/Feature/AuditEndpointsTest.php` |
| `#111` | e2e | The whole demo path: add a page, run an audit, read the score, the top fixes and the section cards — including once at 390px wide | `tests/e2e/audit-flow.spec.ts` |
| `#112` | e2e | A failed audit shows a readable message and a Try again button rather than an empty screen | `tests/e2e/unhappy-path.spec.ts` |

## Known gaps and debt

| # | Item | Priority |
|---|---|---|
| `#121` | The plan's Analytics and Priority-tasks screens are not in V1 | low |
| `#119` | No trend chart, because there is no score history to chart | medium |
| — | Recharts was deliberately cut. The only chart left is a score dial, which is about 20 lines of SVG. Add the library back when the trend chart lands, not before | low |
