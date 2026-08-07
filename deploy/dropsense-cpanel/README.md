# DropSense AI

Google Analytics tells you 62% of people leave. It never tells you why.

Paste a landing page address and get back a ranked list of fixes where **every
single item names a real metric, a real number, and a real section of the page** —
then click once to rewrite the copy that is failing.

An insight that cannot do that is discarded, not shown with a caveat. That one
rule is what separates this from a generic design-tips generator.

## Run it

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate

# MySQL, with credentials to match in .env (DB_DATABASE / DB_USERNAME / DB_PASSWORD)
mysql -u root -e "CREATE DATABASE auditor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE auditor_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate

npm run build                 # or: npm run dev
php artisan serve             # http://127.0.0.1:8000
php artisan queue:work        # in a second terminal — the audit runs here
```

Then add a page, click **Run audit**, fill in the numbers.

**A ready-made audit for demoing**, so nothing depends on the network:

```bash
php artisan db:seed --class=DemoAuditSeeder
```

## Which AI it uses

One request per audit — every section picture, the numbers and the section
positions together. Not one request per section. Set `AI_DRIVER` in `.env`:

| `AI_DRIVER` | What it does | Cost per audit |
|---|---|---|
| `stub` *(default)* | Believable findings generated locally. No network, no key. | $0 |
| `gemini` | Gemini 2.5 Flash. Free tier — good for the build week. | ~$0 |
| `claude` | Claude Sonnet 5. Best critique quality. | ~$0.055 |

`CAPTURE_DRIVER=stub` invents a page shape without opening a browser;
`CAPTURE_DRIVER=playwright` photographs the real page.

Both stubs exist so the whole pipeline runs end to end with no key and no
network — which is also the safety net if the wifi dies during a demo.

## Tests

```bash
php artisan test          # 54 unit + feature tests
npx playwright test       # 6 browser tests, desktop and 390px
```

The four correlation rules and the evidence guarantee are pure functions over
plain value objects with no database in sight, which is why they run in 60ms.

The suite runs against the `auditor_test` MySQL database, not an in-memory
SQLite, so strict mode and real JSON columns are exercised by the tests rather
than discovered in the running app.

## Deploying

[docs/deployment-cpanel.md](docs/deployment-cpanel.md) — cPanel on a subdomain,
through the web interface only.

Note its opening warning: real captures need Chromium installed from a command
line. Hosting without shell access runs in stub mode, and reports say so.

## How it works

```
Run audit → photograph the page → ONE AI request over everything
          → four rules join numbers to visual causes
          → rank by (reach x severity x confidence) / effort
          → score six weighted categories → report
```

Four background stages in one `Bus::chain`, because each needs the rows the
previous one wrote and a failure must stop the rest. Starting an audit returns
`201 pending` immediately; the browser polls every 5 seconds.

## What V1 deliberately does not do

No login (**do not deploy this publicly as-is** — see `#113`), no Google
Analytics or Clarity integration, no score history, no weekly schedule.

Full reasoning: `docs/superpowers/specs/2026-08-02-landing-page-auditor-v1-design.md`
Feature docs and the build dashboard: `docs/features/`
Board: `kanban-md board --compact`
