# Production deployment

Everything this app needs on a real server, plus the four things that break first.

**If you read nothing else:**

1. **`npm ci && npm run build`** — `public/build/` is git-ignored. Skipping this ships a
   site with no CSS or JS.
2. **Run a queue worker** — without it, certificate notifications and backups sit at
   `Queued` forever.
3. **Add the scheduler cron** — no cron means no nightly backup, no data-quality audit,
   no queue pruning.
4. **`APP_DEBUG=false` and doc root = `public/`** — otherwise you expose stack traces,
   or `.env` itself.

**Scope:** a host that runs PHP (shared hosting, VPS, or a PHP-capable PaaS). This app
is **not** deployable to serverless platforms — see [Hosting options](#hosting-options).

---

## 1. Host requirements

Check these *before* signing up:

| Requirement | Why |
|---|---|
| **PHP ≥ 8.2** (`composer.json`: `"php": "^8.2"`) | Laravel 12 will not boot below it |
| Extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, `xml` | Laravel 12 baseline |
| `pdo_sqlite` **or** `pdo_mysql` | whichever database you pick below |
| **Composer 2** | `composer install --no-dev` |
| **Node `^20.19.0` \|\| `>=22.12.0`** *or* the ability to upload built files | Vite 7's own `engines` requirement — Node 18 fails the build. `public/build/` is **git-ignored**, so a bare `git clone` ships no CSS/JS. Both lock files are committed, so `npm ci` works. |
| Writable `storage/` and `bootstrap/cache/` | Blade compilation, sessions, logs, uploads, backups |
| **Cron** | 5 scheduled jobs (below) |
| **A place to run a long process** | queue worker — supervisor/systemd, or a cron fallback |
| **HTTPS** | sessions carry auth cookies |

Doc root must be **`public/`**, not the project root. If the web server serves the
project root, `.env` and `database/` become downloadable.

---

## 2. Choose a database

`config/database.php` ships all five Laravel connections (`sqlite`, `mysql`, `mariadb`,
`pgsql`, `sqlsrv`), but only **SQLite and MySQL are actually exercised** — SQLite runs the
whole test suite, MySQL is what local development uses. Treat the other three as untested.

**In-app backups work on SQLite, MySQL and MariaDB.** `BackupService` writes a
byte-for-byte copy of the SQLite file, and an importable `.sql` script for the two MySQL
dialects — from both the admin **Backup** button and the 03:00 scheduled job. `pgsql` and
`sqlsrv` are rejected with a clear error rather than failing nightly.

The MySQL writer is pure PHP over PDO: it needs no `mysqldump` binary and no `exec()`.
See [Backups](#backups) for what each format contains and how to restore one.

### Option A — SQLite (smallest footprint for a single office)

```env
DB_CONNECTION=sqlite
# DB_DATABASE unset is correct — config/database.php resolves
# database_path('database.sqlite') to an absolute path for you.
```

Why it fits this app:

- `config/database.php` is explicitly tuned for it — WAL journal + `busy_timeout=5000`,
  commented as being for *"the office's small deployments"*.
- The full test suite runs on SQLite (`phpunit.xml`: `DB_CONNECTION=sqlite`, `:memory:`),
  so it is a first-class path, not a fallback.
- **A backup is one file copy** — no export step, no dialect to translate.
- Nothing to install, patch, or monitor.

Note WAL writes `*.sqlite-wal` and `*.sqlite-shm` sidecar files. Do **not** back up with
a plain `cp` while the app is live — you can copy a torn state. Use the in-app backup,
or `sqlite3 database.sqlite ".backup out.sqlite"`.

### Option B — MySQL / MariaDB (what local development uses)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=barangay_management
DB_USERNAME=...
DB_PASSWORD=...
```

Nothing extra is required for backups — the same button and the same 03:00 job work.
Worth knowing:

- The writer is pure PHP, so it does not need `mysqldump` or `exec()` to be enabled —
  both are frequently restricted on shared hosting.
- It streams to disk as it goes, so the database never has to fit in PHP's memory.
- Rows are read inside one transaction, so the file describes a single moment even if
  an office worker saves a record mid-backup.
- Keeping one copy **outside** the app is still wise:

```cron
0 2 * * * mysqldump --single-transaction --routines --triggers -u USER -p'PASSWORD' DBNAME | gzip > /backups/db-$(date +\%F).sql.gz
```

---

## 3. Production `.env`

`.env.example` is the local template and ships `APP_ENV=local`,
`APP_DEBUG=false`, `APP_URL=http://localhost`. **`APP_ENV` and `APP_URL`
must change** (`APP_DEBUG=false` is already production-safe; keep it that
way). Complete production file:

```env
APP_NAME="Barangay Management System"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

# Report boundaries, "today", and every printed/CSV timestamp are local.
# UTC would shift the reporting day by eight hours.
APP_TIMEZONE=Asia/Manila

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

# --- Database: uncomment ONE block from section 2 above ---
# DB_CONNECTION=sqlite
#
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=barangay_management
# DB_USERNAME=...
# DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
# Required on any HTTPS production host — browsers drop the session cookie
# without it. Keep HTTP_ONLY=true and SAME_SITE=lax unless you have a
# cross-site reason to change them (see .env.example + config/session.php).
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your.address@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_FROM_ADDRESS="your.address@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Then generate the key (never reuse your local one):

```bash
php artisan key:generate
```

> **If your host is a container/PaaS that logs to stdout** (Render, Railway, Docker),
> set `LOG_CHANNEL=stderr` instead of `stack`/`single`, so logs reach the platform's log
> stream rather than an unreachable `storage/logs/laravel.log`.

`SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION` all being `database` is intentional —
no Redis/Memcached is required. They cost you three tables and a worker process, and save
you an entire service to provision.

---

## 4. Deploy steps

```bash
# 1. Code
git clone https://github.com/Jeford1005/Barangay_System.git app
cd app

# 2. PHP dependencies (no dev packages on a server)
composer install --no-dev --optimize-autoloader

# 3. Environment
cp .env.example .env
#    ...edit .env per section 3...
php artisan key:generate

# 4. Frontend build — REQUIRED. public/build/ is not in git.
npm ci
npm run build

# 5. Database
php artisan migrate --force
```

**Do not run `php artisan db:seed` in production.** `DatabaseSeeder` creates
`admin@barangay.local`, `staff@barangay.local`, and `official@barangay.local` with the password `password`, plus
sample puroks and households. Those are development credentials — seeding them on a
public host hands anyone the admin account.

```bash
# 6. Permissions (Debian/Ubuntu Apache — adjust user for your host)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 7. Cache — run these LAST, and again whenever .env changes.
#    Always clear BEFORE rebuilding: otherwise a stale cache survives and
#    the new values never take effect (same order the Vercel entrypoint uses
#    on every boot: clear, then cache).
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

> **File-store disk growth.** With `CACHE_STORE=file`, entries accumulate under
> `storage/framework/cache/data`. The `cache:clear` in the deploy sequence above
> prunes the stale files; if the host stays on the file driver long-term, repeat
> `php artisan cache:clear` on each deploy so the directory does not grow
> unbounded. (`SESSION_DRIVER`/`CACHE_STORE`/`QUEUE_CONNECTION` on `database`
> avoid this entirely — see section 3.)

Two things you do **not** need:

- `php artisan storage:link` — resident photos live on the *private* `local` disk
  (`storage/app/private/residents`) and are streamed through `ResidentPhotoController`,
  which checks authorization. Nothing is served from `storage/app/public`.
- `php artisan optimize` — fine to use, but it bundles `route:cache` + `view:cache`.
  If you ever need to revert after editing `.env` or `routes/`: `php artisan config:clear`
  then re-cache.

`route:cache` **does** work here even though `routes/web.php` uses closures — Laravel 12
serialises them through `laravel/serializable-closure` (confirmed installed).

---

## 5. Processes, and the free shared-host path

Nothing in the web request path blocks, but two things silently stop working without a
scheduler and a worker. 5a and 5b explain what they are; **5c** covers getting both — and
the rest of the app — onto a free cPanel account with no terminal and no SSH.

### 5a. Scheduler (cron)

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

> **Windows (XAMPP) alternative — no cron available.** Use Task Scheduler to run
> the same command every minute (see README for the full snippet):
> `schtasks /create /tn "BarangayApp schedule:run" /tr "php C:\path\to\app\artisan schedule:run" /sc minute /mo 1`.
> Every job in `routes/console.php` carries `withoutOverlapping()->onOneServer()`,
> so a slow run never double-fires when the next minute ticks over.

What it runs, from `routes/console.php`:

| Time | Task |
|---|---|
| 02:00 daily | `data:quality-audit` — data integrity report |
| 02:30 daily | `queue:prune-batches --hours=168` |
| 03:00 daily | **Database backup job** |
| 03:30 daily | `queue:prune-failed --hours=24` |
| Hourly at :15 | `system:health --json` |

Without cron, none of these run — no audit, no backups, and `failed_jobs`/`jobs` tables
grow forever.

### 5b. Queue worker — **required**

Notifications are `ShouldQueue` (5 of them) and backups are dispatched as jobs. With no
worker, requests sit at status **Queued** forever: certificate notifications never send,
backup runs never leave `Queued`.

**Preferred — supervisor:**

```ini
[program:barangay-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/app/artisan queue:work --sleep=3 --tries=3 --timeout=60 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/app/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status barangay-worker:*
```

**Fallback — cron only** (no SSH access). `flock -n` stops a second run starting while
the first is still busy:

```cron
* * * * * cd /path/to/app && flock -n storage/framework/queue.cron php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

### 5c. Free / shared cPanel hosting (no SSH)

For a class project or capstone: no card, no expiry, no terminal. Everything below is
arranged so you never need shell access — with one exception, migrations, which
[5c.4](#5c4-getting-the-schema-in-without-a-terminal) solves three different ways.

#### 5c.1 Check the host before you sign up

| Check | Where |
|---|---|
| **PHP ≥ 8.2** + `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo` | cPanel → **Select PHP Version** |
| `.htaccess` / `mod_rewrite` honoured | upload a test rewrite |
| MySQL/MariaDB **and** phpMyAdmin | cPanel → phpMyAdmin |
| FTP or File Manager (archive/zip extraction is a big time-saver) | — |
| Free SSL issued automatically | — |
| **Minute-level cron**, at least 2 entries allowed | cPanel → Cron Jobs |
| **Activity rule** — some free hosts suspend low-traffic accounts | host's TOS |

Size is a non-issue: the app plus `vendor/` plus `public/build/` is a few tens of MB,
against 5 GB-class quotas. `node_modules/` is never uploaded.

> **The activity rule matters more than it sounds.** A demo site nobody opens for a month
> can be suspended the week before your defence. Point a free uptime monitor at it, or put
> a recurring calendar reminder to visit it.

#### 5c.2 Where the files go

**Case A — the host lets you set the document root** (cPanel → Domains → Document Root,
or MultiPHP). Point it at `public/`. You are done: sections 4, 5a and 5b apply unchanged,
and `public/.htaccess` already ships with the right rewrites. **Check for this first.**

**Case B — the document root is fixed** at `htdocs/` or `public_html/`. Put the project
*outside* the web root and leave only a front controller in it:

```
~/                          FTP root — never served over HTTP
├── barangay/               the Laravel project (.env, vendor/, storage/, database/)
└── htdocs/                 ← document root, the only web-served folder
    ├── index.php           shim
    ├── .htaccess
    ├── build/              copy of barangay/public/build
    └── favicon.ico         …the rest of barangay/public/*
```

`htdocs/index.php`:

```php
<?php
// Front controller for hosts with a fixed document root.
require __DIR__ . '/../barangay/public/index.php';
```

That is the whole trick. `public/index.php` builds every path from its *own* `__DIR__`,
so being required from elsewhere changes nothing — `vendor/autoload.php` and
`bootstrap/app.php` still resolve. Apache sets `SCRIPT_NAME` to `/index.php`, so Laravel
derives a base path of `/` and generates correct absolute URLs, while `REQUEST_URI` passes
through untouched and routing works as normal.

`htdocs/.htaccess` serves real files (your built CSS/JS) directly and hands everything
else to the shim:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

Because the project lives one level above `htdocs/`, `.env`, `vendor/`, `storage/` and
`database/` are simply not reachable by URL — you get the §1 doc-root guarantee without
being able to change the doc root.

> If your host serves its **entire** FTP root (no `htdocs/` subfolder), do not try to
> secure Laravel by rewriting rules — you would be deny-listing an attack surface you
> cannot fully enumerate. Pick a host that gives you a subfolder, or use Case A.

#### 5c.3 Build locally, upload once

`public/build/` and `vendor/` are both git-ignored, so the repo alone ships neither CSS
nor PHP dependencies. Build on your laptop, then upload in an order that never leaves the
live URL half-working:

```bash
npm ci && npm run build
composer install --no-dev --optimize-autoloader
php artisan config:clear
```

Generate the production key locally — this is what replaces `key:generate` on a host with
no terminal:

```bash
php artisan key:generate --show      # copy the base64:… value
```

Zip the project excluding `.git`, `node_modules/`, `storage/logs/`, `storage/framework/{cache,sessions,views}/`, `database/*.sqlite`, and **your local `.env`**.

Then, in this order:

1. Upload the zip to `~/` (above the web root) and extract → `~/barangay/`.
2. Create `~/barangay/.env` with the File Manager from [§3](#3-production-env), pasting
   in the `APP_KEY` from `key:generate --show` above. Never upload your local `.env`.
3. Set `storage/` and `bootstrap/cache/` writable (File Manager → Permissions → 775,
   recursive).
4. Upload `public/build/` → `htdocs/build/`, and the rest of `public/` → `htdocs/`.
5. Upload `htdocs/index.php` (the shim) and `htdocs/.htaccess` **last.**

Step 5 is what makes the deploy atomic: until the shim lands, the site is either
untouched or blank; it flips to working in one action rather than passing through a
broken intermediate state.

#### 5c.4 Getting the schema in without a terminal

You need tables before anything boots. Three options, best first:

- **Host has a Terminal feature** — use it and run [§4](#4-deploy-steps) as written.
  Not all free hosts expose this.
- **phpMyAdmin (no terminal needed)** — migrate a scratch database on your laptop (seed it
  too, if this copy is for a demo), export it (phpMyAdmin → Export → *Custom*, format SQL),
  then Import into the host's database. You get schema *and* your data in one step, and
  `artisan` never runs on the server.
- **One-time HTTP route** — if the host gives you neither, bootstrap a token-guarded
  route that calls `Artisan::call('migrate')`, hit it once, then delete the route. The
  same pattern is documented in [vercel-deploy.md](vercel-deploy.md).

> **Demo credentials.** §4 says never seed in production, and that still holds for a real
> office install — `DatabaseSeeder` creates `admin@barangay.local` with the password
> `password`. On a *class demo* you need a login, so either seed deliberately and change
> every password immediately, or import a database where you have already set a real one.
> Treat a seeded public URL as an open admin account, because it is.

#### 5c.5 The two cron entries

cPanel → **Cron Jobs**. Add both; `cd` first so relative paths in the app resolve:

```cron
* * * * * cd /home/USER/barangay && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/USER/barangay && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

- Confirm the PHP path with `Select PHP Version` or `which php` — `/usr/local/bin/php` is
  common on cPanel but not universal.
- cPanel's own editor escapes `%` for you; if you ever paste a cron line containing `%`
  yourself, write `\%`.
- The worker uses `--stop-when-empty --max-time=50` instead of `flock`, because some free
  hosts strip `flock` out of the shell. Both bound a run to under a minute, so an overlap
  is harmless.

**If your host only allows hourly cron**, move the schedule times in `routes/console.php`
to the top of the hour — `schedule:run` only fires jobs due at that exact minute, so
`dailyAt('02:30')` would never run on an hourly `:00` cron. `hourlyAt(15)` becomes
`hourly()`.

#### 5c.6 Demo day

Do not demo from one URL. Have both ready and tested the night before:

1. **Local, offline** — `php artisan serve` or XAMPP. No internet, no host, no uptime
   screen in front of your panel.
2. **Hosted** — proves it is really deployed. Log in on it once while you still have
   time to fix something.

Run the §6 checklist against the hosted copy: `/login` returns 200 **and is styled**,
`/your-domain/.env` returns 404, and a request moves off `Queued` — on a free host that
last one is your proof the cron worker is actually alive.

---

## 6. Verify before you hand it over

```bash
php artisan about              # APP_ENV must say "production", cache status "enabled"
php artisan migrate:status     # every migration green
php artisan system:health --json
php artisan mail:check         # config audit
php artisan mail:check --send  # actually delivers a test email — do this before
                               # anyone relies on password reset
```

Browser:

1. `https://your-domain.com/login` returns 200 **and is styled** — unstyled means
   `npm run build` didn't run or the doc root is wrong.
2. Log in as an office user; then as a resident (`/my`).
3. Submit a certificate request and confirm it moves off `Queued` — this proves the
   worker is alive.
4. Check `storage/logs/laravel.log` for errors after exercising the app.
5. Confirm `.env` is **not** reachable: `https://your-domain.com/.env` must 404.

---

## Hosting options

| | Fit | Notes |
|---|---|---|
| **VPS + Forge/Ploi** (DigitalOcean, Hetzner, Vultr) | ✅ Best | Full control; supervisor + cron are trivial |
| **Shared PHP hosting** (cPanel/Plesk) | ✅ Fine | Cheapest, and the only row that needs no card. Step-by-step, including the fixed-document-root case: [§5c](#5c-free--shared-cpanel-hosting-no-ssh) |
| **PHP-capable PaaS** (Render, Railway) | ✅ Fine | Managed cron + worker; use `LOG_CHANNEL=stderr` |
| **Laravel Vapor** | ⚠️ Works, costs | Needs S3 for photos and a rethink of backups |
| **Vercel (container service)** | ⚠️ Demo only | Works via FrankenPHP — see [vercel-deploy.md](vercel-deploy.md). Needs an external Postgres, loses uploads on redeploy, no worker, and Hobby cron is limited to one fire per day. |

> I have not verified current free-tier limits or pricing — confirm with the provider
> before committing to one.

**Why Vercel is still not a fit for a live office install:** this rewrite is
Laravel — it needs a PHP runtime, a MySQL/SQLite database (Vercel has neither), a
**writable** filesystem for Blade compilation and photo uploads (Vercel gives
read-only + `/tmp`), and a **queue worker** (Vercel has no long-running
processes). PHP itself *is* now possible on Vercel by packaging the app as a
FrankenPHP container — `Dockerfile.vercel` + `vercel.json` in this repo do
exactly that, and [vercel-deploy.md](vercel-deploy.md) walks through it — but
the database, storage and worker gaps remain, so treat it as a demo target
rather than a deployment target.

Note that a Vercel project pointed at this repo **without** those two files
fails outright: Vercel auto-detects the Vite preset, runs only `npm run build`,
and then reports `No Output Directory named "dist" found`. That happened because
`public/build` is the Vite output directory (the `laravel-vite-plugin` default)
and no `dist/` is ever created.

---

## Backups

Back up **three** things, not one:

1. **Database** — the in-app backup handles SQLite, MySQL and MariaDB alike.
2. **`storage/app/private/residents`** — resident photos. Personal data with no other copy.
3. **`.env`** — especially `APP_KEY`. Losing it makes encrypted sessions/data unreadable.

Run one from **Admin → Settings → Maintenance → Create backup**, or let the 03:00 job do
it. Backups land in `storage/app/private/backups`, keep the newest 10, and are never
placed under `public/`. Confirm that directory survives your host's deploy cycle — on
platforms that replace the whole release folder on every deploy, **`storage/` must be a
mounted/persistent volume** or you will silently lose uploads between deploys.

### Restoring

There is no restore button. Stop the queue worker and the web server first, and take a
fresh backup before you start — a restore discards everything written since the snapshot.

**SQLite** — the file *is* the database:

```bash
rm -f database/database.sqlite-wal database/database.sqlite-shm   # stale WAL would win
cp storage/app/private/backups/barangay-YYYYMMDD-HHMMSS.sqlite database/database.sqlite
php artisan migrate --force    # only if the backup predates a migration
```

**MySQL / MariaDB** — the file is an importable script that **overwrites every table it
contains**:

```bash
mysql -u USER -p barangay_management < storage/app/private/backups/barangay-YYYYMMDD-HHMMSS.sql
```

The script is written to import cleanly as-is: it sets `NAMES utf8mb4`, disables
foreign-key checks around the load, drops each table before recreating it, and sets
`NO_AUTO_VALUE_ON_ZERO` so an `id` of `0` comes back as `0` rather than as the next
auto-increment value. Column data — quotes, newlines, emoji, `NULL`, DECIMALs — round-trips
byte for byte; that is covered by a restore test in the suite.

---

## Updating a live install

```bash
cd /path/to/app
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart barangay-worker:*
```

Use `php artisan down` / `php artisan up` around the migration if the schema change is
not backward compatible.

---

## When it goes wrong

| Symptom | Cause | Fix |
|---|---|---|
| White screen, no error | `APP_DEBUG=false` + an exception | Check `storage/logs/laravel.log` |
| Unstyled pages, JS 404 | Assets never built | `npm ci && npm run build` |
| Requests stuck `Queued` | Worker not running | §5b |
| Scheduled jobs never run | No cron | §5a |
| Backup run `Failed` nightly | Unsupported driver, disk full, or DB unreachable | `error_message` on Admin → Maintenance, then [Backups](#backups) |
| `No application encryption key` | `.env` copied without `key:generate` | `php artisan key:generate` |
| 500 after editing config | Stale cache | `php artisan config:clear` then re-`config:cache` |
| `SQLSTATE[HY000] [2002] Connection refused` | `DB_HOST=127.0.0.1` but DB is remote | Set the real host, or use SQLite |
| Uploads fail / disk errors | `storage/` not writable | `chmod -R 775 storage bootstrap/cache` |
| Fixed doc root: every URL 500s | Shim missing or pointing at the wrong folder | `htdocs/index.php` must `require __DIR__.'/../barangay/public/index.php'` — [§5c.2](#5c2-where-the-files-go) |
| Unstyled pages **only** on the free host | `public/build/` never reached the doc root | Re-upload `public/build` → `htdocs/build` |
| Nothing scheduled on a free host | Cron entries not added, or host caps frequency | [§5c.5](#5c5-the-two-cron-entries) |
