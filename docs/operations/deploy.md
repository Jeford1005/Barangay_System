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

## 2. Pick the database first — it decides your backup story

`config/database.php` ships all five Laravel connections (`sqlite`, `mysql`, `mariadb`,
`pgsql`, `sqlsrv`), but only **SQLite and MySQL are actually exercised** — SQLite runs the
whole test suite, MySQL is what local development uses. Treat the other three as untested.

Either way, **the in-app backup feature only works on SQLite.**

`App\Services\BackupService::create()` opens with:

```php
if (config('database.default') !== 'sqlite') {
    throw new RuntimeException('Automatic backup creation is currently enabled for SQLite only.');
}
```

It uses `VACUUM INTO`, a SQLite statement — there is no `mysqldump` call anywhere in
the codebase. Consequence on MySQL: the **03:00 scheduled backup job fails every night**,
writes a `Failed` row to `backup_runs`, and rethrows into `failed_jobs`.

### Option A — SQLite (recommended for a single barangay office)

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
- **In-app backups work** (Admin → Backup, plus a nightly scheduled one).
- Nothing to install, patch, or monitor.

Note WAL writes `*.sqlite-wal` and `*.sqlite-shm` sidecar files. Do **not** back up with
a plain `cp` while the app is live — you can copy a torn state. Use the in-app backup,
or `sqlite3 database.sqlite ".backup out.sqlite"`.

### Option B — MySQL / MariaDB

Your local development uses this (`DB_HOST=127.0.0.1`, `DB_DATABASE=barangay_management`).
Use it in production only if you already have MySQL infrastructure.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=barangay_management
DB_USERNAME=...
DB_PASSWORD=...
```

**You must replace the in-app backup with an external job:**

```cron
0 2 * * * mysqldump -u USER -p'PASSWORD' DBNAME | gzip > /backups/db-$(date +\%F).sql.gz
```

Otherwise the nightly 03:00 job fails continuously. The manual **Backup** button in the
admin area will also fail on MySQL — that is expected, not a bug you introduced.

---

## 3. Production `.env`

`.env.example` is the local template and ships `APP_ENV=local`, `APP_DEBUG=true`,
`APP_URL=http://localhost`. **All three must change.** Complete production file:

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
`admin@barangay.local` and `staff@barangay.local` with the password `password`, plus
sample puroks and households. Those are development credentials — seeding them on a
public host hands anyone the admin account.

```bash
# 6. Permissions (Debian/Ubuntu Apache — adjust user for your host)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 7. Cache — run these LAST, and again whenever .env changes
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

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

## 5. Two processes that must keep running

Nothing in the web request path blocks, but two things silently stop working without these.

### 5a. Scheduler (cron)

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

What it runs, from `routes/console.php`:

| Time | Task |
|---|---|
| 02:00 daily | `data:quality-audit` — data integrity report |
| 02:30 daily | `queue:prune-batches --hours=168` |
| 03:00 daily | **Database backup job** |
| 03:30 daily | `queue:prune --hours=24` |
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
| **Shared PHP hosting** (cPanel/Plesk) | ✅ Fine | Cheapest. Need SSH or a cron panel; set doc root to `public/`; confirm PHP ≥ 8.2 |
| **PHP-capable PaaS** (Render, Railway) | ✅ Fine | Managed cron + worker; use `LOG_CHANNEL=stderr` |
| **Laravel Vapor** | ⚠️ Works, costs | Needs S3 for photos and a rethink of backups |
| **Serverless — Vercel, Netlify, AWS Lambda** | ❌ No | No PHP runtime, read-only filesystem, no long-running worker |

> I have not verified current free-tier limits or pricing — confirm with the provider
> before committing to one.

**Why Vercel specifically fails here:** your GitHub history shows the *previous* plain-PHP
version targeted Vercel ("ASSET_VERSION cache-buster … on Vercel"). This rewrite is Laravel —
it needs a PHP runtime, a MySQL/SQLite database (Vercel has neither), a **writable**
filesystem for Blade compilation and photo uploads (Vercel gives read-only + `/tmp`), and a
**queue worker** (Vercel has no long-running processes). A Vercel project pointed at this
repo will fail its build or deploy a site that 404s on every route.

---

## Backups

Back up **three** things, not one:

1. **Database** — in-app backup (SQLite) or `mysqldump` cron (MySQL).
2. **`storage/app/private/residents`** — resident photos. Personal data with no other copy.
3. **`.env`** — especially `APP_KEY`. Losing it makes encrypted sessions/data unreadable.

In-app backups land in `storage/app/private/backups`, keep the newest 10, and are never
placed under `public/`. Confirm that directory survives your host's deploy cycle — on
platforms that replace the whole release folder on every deploy, **`storage/` must be a
mounted/persistent volume** or you will silently lose uploads between deploys.

---

## Updating a live install

```bash
cd /path/to/app
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
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
| Backup run `Failed` nightly | MySQL — backups are SQLite-only | §2 Option B |
| `No application encryption key` | `.env` copied without `key:generate` | `php artisan key:generate` |
| 500 after editing config | Stale cache | `php artisan config:clear` then re-`config:cache` |
| `SQLSTATE[HY000] [2002] Connection refused` | `DB_HOST=127.0.0.1` but DB is remote | Set the real host, or use SQLite |
| Uploads fail / disk errors | `storage/` not writable | `chmod -R 775 storage bootstrap/cache` |
