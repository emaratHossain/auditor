# F01 — Landing pages (add, list and delete)

**One line:** Somewhere to paste a landing page address, see the pages you have added, and
remove the ones you are done with.

- **Mode:** plan · **Status:** planned — nothing built yet · **Epic:** `#68`
- **Visual doc:** [`v1.dashboard.html`](v1.dashboard.html) — open it in a browser
- **Tasks:** `kanban-md list --compact --tag f01-pages`

## Flow

```mermaid
flowchart LR
    A["Someone types a name and a web address<br/><i>Pages.jsx</i>"] --> B["We check it is a real address<br/><i>StorePageRequest.php</i>"]
    B --> C["We save the page<br/><i>pages table</i>"]
    C --> D["We decide what the screen is allowed to see<br/><i>PageResource.php</i>"]
    D --> E["The page appears in the list with its latest score<br/><i>Pages.jsx</i>"]
    classDef check fill:#f4e6cc,stroke:#b5771f,color:#5a3d0d;
    classDef store fill:#d7ebe8,stroke:#0f766e,color:#0f3d38;
    class A,E store;
    class B,D check;
    class C store;
```

**Why a Resource, never the model.** A Resource class is the only place that decides what
the browser sees. That means adding a database column later can never accidentally leak it
into the API.

## Deleting a page

`DELETE /api/pages/{page}` → `204`. The controller hands off to `PageEraser`, which is the
only class that knows an audit owns files.

```mermaid
flowchart LR
    A["Delete, then a dialog naming the page<br/><i>Pages.jsx · ConfirmDialog</i>"] --> B["Collect the audit ids first<br/><i>PageEraser.php</i>"]
    B --> C["Delete the page in a transaction<br/><i>SQLite cascades to every child row</i>"]
    C --> D["Then remove the pictures and PDFs<br/><i>screenshots/{id}, audit-{id}.pdf</i>"]
    classDef check fill:#f4e6cc,stroke:#b5771f,color:#5a3d0d;
    classDef store fill:#d7ebe8,stroke:#0f766e,color:#0f3d38;
    class A,C,D store;
    class B check;
```

**Why the ids are collected before the delete.** The cascade runs inside SQLite, so
Eloquent's `deleting` events never fire for the audits. An observer on `Audit` would simply
never run, and every screenshot would be orphaned.

**Why rows first and files after.** If file removal fails you are left with images nobody
references — disk, and nothing else. The other order leaves live audits pointing at
screenshots that are gone, which is a report screen full of broken pictures.

**Why the dialog counts the reports.** "Are you sure?" about an unnamed row is not a
question anyone can answer. The dialog names the page and says what else goes with it,
because nothing here can be undone. `audits_count` comes from `withCount` on the index.

**Why the jobs go quiet.** Deleting a page mid-audit leaves up to four chained jobs holding
an id that no longer resolves. Each returns without a word rather than throwing — a
deliberate deletion should not read as a crash in the log.

## What "done" means

- [ ] **A page can be added** — paste a name and a URL, and it appears in the list · `#81` `#85`
- [ ] **A bad address is caught by the server** — type `not-a-url`, and the error appears under the URL input, read from the server's 422 reply · `#81` `#85`
- [ ] **Validation is not written twice** — the browser does not re-implement the rules; the server is the only place validation is real · `#85`
- [ ] **The list shows something useful** — each row carries the latest health score as a chip, or a dash if the page has never been audited · `#85`
- [ ] **No blank white area** — loading skeleton, empty state, and an error state with a retry all render · `#85`
- [x] **A page can be deleted** — Delete on the row, a dialog that names the page and counts its reports, and the row is gone; Cancel and Escape both mean nothing happened
- [x] **Deleting takes the pictures with it** — every audit's screenshot directory and exported PDF go too, so disk does not fill up with files nobody references

## Tests

| # | Kind | What it proves | Target file |
|---|---|---|---|
| `#110` | feature | A valid page is created and returned; an invalid URL returns 422 with a field-level message | `tests/Feature/AuditEndpointsTest.php` |
| `#111` | e2e | Add a page in the browser and see it in the list | `tests/e2e/audit-flow.spec.ts` |
| `#112` | e2e | An empty URL shows an error under the field, not just a toast | `tests/e2e/unhappy-path.spec.ts` |
| — | feature | Deleting takes the audits, every child row, the screenshots and the PDFs — and leaves other pages untouched | `tests/Feature/DeletePageTest.php` |
| — | e2e | Delete removes the row and it stays gone after a reload; Cancel and Escape do nothing; the dialog counts the reports | `tests/e2e/delete-page.spec.js` |

## Known gaps and debt

| # | Item | Priority |
|---|---|---|
| `#113` | No `user_id` on pages and no policy — every page is visible to everyone | critical |
| — | The optional `section_selectors` field exists in the table but has no UI in V1. It is the escape hatch when automatic section detection guesses badly (see `#88`) | medium |
