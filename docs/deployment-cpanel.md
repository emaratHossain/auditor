# Deploy to cPanel — UI only, no terminal

Everything below happens in the cPanel web interface. No SSH, no command line.

## Read this first

**Without a terminal you cannot install Chromium, so real audits will not run.**
Screenshots, Lighthouse and PDF export all need a browser installed from the command
line. This deploy runs in **stub mode**: the full app, real database, real pipeline,
real reports — but the page is never actually photographed, and reports label
themselves as simulated.

That is fine for a demo or a client walkthrough. For real audits you need a VPS.

## Two files are prepared for you

| File | What it is |
|---|---|
| `deploy/dropsense-cpanel.zip` (14 MB) | The whole app, dependencies and frontend already built |
| `deploy/dropsense-schema.sql` | All 18 tables, ready to import |

Both are built and verified. You only upload.

---

## 1. PHP version

*Software → MultiPHP Manager* → select the subdomain → **PHP 8.2** or newer.

*Software → Select PHP Version → Extensions* — tick: `pdo_mysql` `mbstring` `openssl`
`tokenizer` `xml` `ctype` `json` `bcmath` `fileinfo` `curl` `zip`

## 2. Subdomain

*Domains → Create A New Domain*

- Domain: `dropsense.yourdomain.com`
- **Untick "Share document root"**
- Document Root: **`/home/USER/dropsense/public`**

The `/public` is required. Point it at `/home/USER/dropsense` and anyone can download
your `.env` with its password and API key.

Then *Security → SSL/TLS Status* → **Run AutoSSL**.

## 3. Upload the app

*Files → File Manager* → navigate to `/home/USER/` (**not** `public_html`)

1. **+ Folder** → `dropsense`
2. Enter it → **Upload** → `dropsense-cpanel.zip`
3. Back in File Manager, right-click the zip → **Extract**
4. Delete the zip

You should now see `app/`, `public/`, `vendor/`, `artisan` inside `/home/USER/dropsense/`.

## 4. Database

*Databases → MySQL Databases*

1. **Create Database:** `dropsense` → becomes `USER_dropsense`
2. **Add New User:** `dsuser` + a strong password → becomes `USER_dsuser`
3. **Add User To Database** → tick **ALL PRIVILEGES**

*Databases → phpMyAdmin* → select `USER_dropsense` → **Import** tab →
choose `dropsense-schema.sql` → **Go**.

You should get "18 tables". No migration command needed — the file already records all
17 migrations as applied.

## 5. Create `.env`

In File Manager, inside `/home/USER/dropsense/`:

**Settings** (top right) → tick **Show Hidden Files (dotfiles)** → Save.
Without this you will not see the file you are about to make.

**+ File** → name it `.env` → right-click → **Edit** → paste:

```ini
APP_NAME="DropSense AI"
APP_ENV=production
APP_KEY=base64:9sgYFx6WwUbO8wp/DOmX5O9Ug49Zik/7c9ZmgCo1Rzg=
APP_DEBUG=false
APP_URL=https://dropsense.yourdomain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=USER_dropsense
DB_USERNAME=USER_dsuser
DB_PASSWORD=your-password-here

SESSION_DRIVER=database
QUEUE_CONNECTION=database

# The worker here is started by cron, not by hand, so there is a short gap
# between one worker exiting and the next starting. Below that gap the app
# reports a healthy deployment as broken. See step 7.
AUDIT_PENDING_TIMEOUT=120

CACHE_STORE=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
MAIL_MAILER=log

# No browser available on this host
CAPTURE_DRIVER=stub

# stub = free, invented findings. Swap to gemini + a key for real AI copy.
AI_DRIVER=stub
AI_REWRITE_DRIVER=stub
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.6-flash
AI_MAX_COST_PER_AUDIT=0.25
```

Change **`USER_`**, the **password**, and **`APP_URL`**. The `APP_KEY` above was generated
for you — keep it, or the app cannot decrypt its own sessions.

`APP_URL` must be the exact `https://` address. Screenshot links are signed against it;
wrong value means every image 403s.

Then right-click `.env` → **Permissions** → **`0600`**.

## 6. Folder permissions

In File Manager, right-click → **Permissions**, tick **Recurse into subdirectories**:

- `storage` → **755**
- `bootstrap/cache` → **755**

If you get a 500 error later, come back and try **775**.

## 7. Queue worker — required

Audits run in the background. **Without this they sit on "pending" forever.**

*Advanced → Cron Jobs → Add New Cron Job*

**Common Settings:** Every 5 Minutes (`*/5 * * * *`)

**Command:**

```
/usr/local/bin/php /home/USER/dropsense/artisan queue:work --tries=1 --timeout=300 --max-time=290 --sleep=3 >> /home/USER/dropsense/storage/logs/queue.log 2>&1
```

Replace `USER`. If it does not run, try `/usr/local/bin/ea-php83` instead of
`/usr/local/bin/php` — *MultiPHP Manager* shows which version you are on.

**There is no `--stop-when-empty` here, and that is the whole trick.** With it, the
worker sees an empty queue, exits in under a second, and nothing is listening for the
next five minutes — so an audit started at 12:01 waits until 12:05 before anything
happens. Without it the worker stays alive polling the jobs table until `--max-time`
retires it at 290s, ten seconds before the next cron starts a fresh one. A worker is
alive roughly 97% of the time and audits start within about ten seconds.

`--max-time` is checked between jobs, so a worker that is mid-audit at 290s finishes
that audit before exiting. The next cron may then start a second worker alongside it.
That is intended — it is what keeps the coverage gap at ten seconds — and the workers
take different jobs rather than the same one.

If your host's minimum cron interval is longer than 5 minutes, raise `--max-time` to
match it (interval in seconds, minus 10) and raise `AUDIT_PENDING_TIMEOUT` below to suit.

Clear the **Email** box at the top of the Cron Jobs page, or you get a message every minute.

## 8. Check it

Visit `https://dropsense.yourdomain.com` — the DropSense UI should load.

Add a landing page → **Run audit** → fill in the numbers. A worker is normally already
running, so the audit starts within about ten seconds and you get a Conversion Score
with ranked fixes, marked as simulated. The one slow case is the first audit after you
add the cron job, which waits for the next five-minute tick.

Want something to show immediately? Add this as a **second cron job**, set to *Every 5
Minutes*, let it run once, then **delete it**:

```
/usr/local/bin/php /home/USER/dropsense/artisan db:seed --class=DemoAuditSeeder --force
```

---

## Running any command without a terminal

The trick above works for anything. Add a cron job, set **Every 5 Minutes**, wait, then
delete it. Send output somewhere you can read:

```
/usr/local/bin/php /home/USER/dropsense/artisan <command> >> /home/USER/dropsense/storage/logs/manual.log 2>&1
```

Read `manual.log` in File Manager. Useful ones: `migrate --force`, `config:clear`,
`about`, `queue:failed`.

**Do not run `config:cache`.** It bakes `.env` into a file, and without a terminal a bad
cache is painful to clear. The app runs fine without it.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| Audit stuck on "pending" | Cron not running (step 7). Check `storage/logs/queue.log` exists in File Manager. |
| Audits fail after ~45s saying no worker is running | `AUDIT_PENDING_TIMEOUT` missing from `.env` (step 5). The cron worker is fine; the app is giving up before it starts. |
| 500 on every page | `storage` + `bootstrap/cache` → 775. Check `APP_KEY` is in `.env`. |
| Blank page, no styling | `public/build/` missing — re-extract the zip. |
| Site shows a file listing | Document root missing `/public` (step 2). |
| Refreshing `/audits/42` → 404 | `public/.htaccess` not extracted. Enable Show Hidden Files and check. |
| Screenshots 403 | `APP_URL` does not match the real address. |
| Can't see `.env` | File Manager → Settings → Show Hidden Files. |
| Database connection error | `USER_` prefix missing, or user not added to the database. |
| Errors but no detail | Set `APP_DEBUG=true` briefly, reload, **then set it back to false**. |

Laravel's own errors land in `storage/logs/laravel.log` — readable in File Manager.

## Updating later

1. Rebuild the zip locally, upload, extract over the top (File Manager will ask to overwrite)
2. Do **not** overwrite `.env`
3. New database changes? Run `migrate --force` with the cron trick above

## If you later want real audits

Real screenshots, Lighthouse and PDF need Chromium, which needs command-line install and
root. A $5–10/month VPS with cPanel or plain Ubuntu covers it. At that point set
`CAPTURE_DRIVER=playwright` and `AI_DRIVER=gemini`.
