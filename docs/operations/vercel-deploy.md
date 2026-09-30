# Deploying to Vercel

This app runs on Vercel as a **container service** (FrankenPHP), not as a
serverless PHP function. Vercel has no PHP runtime of its own, so the runtime
ships in the image; the database comes from the Vercel Marketplace, which
injects its credentials as environment variables:

| Piece | Provided by | File |
|---|---|---|
| PHP 8.4 runtime + web server | FrankenPHP image | `Dockerfile.vercel` |
| Document root / routing | Caddy | `Caddyfile` |
| Vercel project wiring | container service + catch-all rewrite | `vercel.json` |
| Database | Neon Postgres provisioned by the **Vercel Marketplace** | env var `DATABASE_URL` (injected) |
| Migrations + demo accounts | `docker/entrypoint.sh` on every container boot | `migrate --force` (already-applied migrations are skipped), then `db:seed --force` **only when the `users` table is empty** |

## Why the first deploy failed

```
Error: No Output Directory named "dist" found after the Build completed.
```

Vercel read `package.json`, saw Vite, and auto-detected the **static site**
preset. Its build log confirms it: it installed 95 npm packages, ran
`npm run build`, and produced `public/build/…` — then looked for a `dist/`
directory that a Laravel app never creates. No PHP was ever installed, no
Composer step ran, and `/vendor` is git-ignored, so even the front controller
had nothing to load.

`vercel.json` now declares a container service and restores a catch-all
rewrite, which is what tells Vercel to stop treating this as a static site.

## Before you deploy

### 1. Create a Postgres database

Install Neon from the **Vercel Marketplace**, which provisions the database and
links it to this project in a single step:

```bash
vercel install neon --name barangay-db --plan free_v3 \
  -m region=iad1 -m auth=false -e production --non-interactive
```

The first attempt may stop with `integration_terms_acceptance_required`. Open
the `accept-terms` URL it prints, accept once, then re-run the same command.

Neon's Free plan needs no card (0.5 GB, 100 compute-hours, 10 databases).
Pick the region matching where the container runs — `iad1` here.

Vercel injects the credentials into the Production environment for you:
`DATABASE_URL` plus `DATABASE_URL_UNPOOLED` and the `PG*` / `POSTGRES_*`
aliases. No connection string is ever pasted into a dashboard or committed.

Two things matter about that injected value:

- **The app uses the unpooled endpoint.** The pooled (`…-pooler`) hostname sits
  behind PgBouncer, and against it Laravel's PDO driver failed every schema
  transaction with `SQLSTATE[25P02] current transaction is aborted` — the very
  same SQL, executed in the very same order against the same pooled endpoint
  from another client, succeeded. `DB_URL` is therefore set to
  `DATABASE_URL_UNPOOLED`. Direct is also the right choice for one container:
  pooling buys nothing when a single instance holds a handful of connections.
- **`DB_PORT` must be `5432`.** Neon's URL omits `:5432`, and Laravel's URL
  parser drops null values before merging, so a port left behind by an earlier
  MySQL configuration would survive and point a Postgres driver at 3306.

> **SQLite will not work on Vercel.** The filesystem is read-only apart from a
> per-instance `/tmp`, so a database file cannot be created or persisted, and
> each instance would see a different empty file.

### 2. Generate an application key

```bash
php artisan key:generate --show
```

Keep this value. Never reuse your local key.

## Environment variables

Set these in **Vercel → Project → Settings → Environment Variables**. Use the
same values for Production and Preview unless you deliberately want separate
data.

| Variable | Value | Notes |
|---|---|---|
| `APP_KEY` | `base64:…` | From the command above. Required. |
| `APP_URL` | `https://<your-project>.vercel.app` | Must be `https://`. |
| `DB_URL` | `postgresql://…` | Set to the **unpooled** string. Optional: Laravel reads `DB_URL` first and falls back to the injected `DATABASE_URL`, so only set it when you want to override which endpoint is used. |
| `DB_CONNECTION` | `pgsql` | Required. Without it the default driver is `sqlite` and no URL variable is consulted at all. |
| `DB_PORT` | `5432` | Required. Overrides any port left over from an earlier MySQL setup (see §1). |
| `APP_TRUSTED_PROXIES` | `vercel` | Makes Laravel see the request as HTTPS (see below). |
| `SESSION_SECURE_COOKIE` | `true` | Optional; cookie hardening. |
| `MAIL_MAILER` | `smtp` | **If this is missing, `config/mail.php` falls back to `log`** — every password-reset email is written to `storage/logs` instead of being sent, with no error shown anywhere. This was a real outage: the project had stale `SMTP_*` vars (47 days old) and zero `MAIL_*`, so resets looked like they worked. |
| `MAIL_HOST` | `smtp.gmail.com` | Gmail's SMTP relay. |
| `MAIL_PORT` | `587` | StartTLS. |
| `MAIL_USERNAME` | your Gmail address | The account the mail is relayed through. |
| `MAIL_PASSWORD` | Gmail **app password** | A 16-character app password, *not* your Google password. Store it as a Secret. |
| `MAIL_FROM_ADDRESS` | the same Gmail address | Must match the relay account or Gmail refuses to send. |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Keep the literal `${APP_NAME}` — Laravel interpolates it at runtime. |

Note that these are `MAIL_*`, not `SMTP_*`. Laravel never reads `SMTP_*`,
so leftover variables under that name do nothing.

`APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`,
`SESSION_DRIVER=database`, `CACHE_STORE=database` and `QUEUE_CONNECTION=sync`
are already baked into the image.

> **Verify mail after every deploy.** `/admin/mail-health` checks the driver,
> the SMTP host/port/credentials, the from-address, and runs a live
> `fsockopen` against `smtp.gmail.com:587`. It shows `Ready` only when every
> check is green; `Needs attention` with a `log` driver means `MAIL_MAILER`
> is missing from the environment.

> **The container seeds once, on a fresh database.** `docker/entrypoint.sh`
> runs `db:seed --force` only when the `users` table is empty, so a fresh
> database is populated with `admin@barangay.local`,
> `staff@barangay.local`, `official@barangay.local` and
> `resident@barangay.local`, all with the password `password`. That is
> deliberate for a demo or stakeholder walkthrough. On later boots — when
> accounts already exist — seeding is skipped, so passwords you have changed
> (or accounts you have deleted) stay that way.
>
> **Before anyone outside the classroom touches it**, change every seeded
> password (or delete the demo accounts and create real ones). The seeder is
> built on `firstOrCreate`, so it never duplicates rows and never overwrites a
> password you have already changed — but on a *fresh* database it will still
> create `password`-only accounts on a public URL.

### Why `APP_TRUSTED_PROXIES` is needed

Vercel terminates TLS at its edge and forwards plain HTTP to PHP with
`X-Forwarded-Proto` / `X-Forwarded-Host`. Without trusting that proxy, Laravel
believes the request is `http://` and generates `http://` asset, form-action
and redirect URLs — which browsers block as mixed content on an `https://`
page. Browsers also drop the auth cookie in that situation.

`bootstrap/app.php` trusts the proxy **only** when `APP_TRUSTED_PROXIES=vercel`.
Local XAMPP is unaffected, and because nothing is trusted by default a direct
request cannot spoof those headers.

## Deploy

```bash
# From the repo root
vercel login

# Provision + link the database first (see §1)
vercel install neon --name barangay-db --plan free_v3 \
  -m region=iad1 -m auth=false -e production --non-interactive

vercel link            # create/link the project

vercel env add APP_KEY production
vercel env add APP_URL production
vercel env add DB_CONNECTION production     # value: pgsql
vercel env add DB_PORT production           # value: 5432
vercel env add APP_TRUSTED_PROXIES production

vercel deploy --prod
```

`vercel env add` prompts for the value, so use `--value` (or stdin) when
scripting:

```bash
echo "pgsql"  | vercel env add DB_CONNECTION production
echo "5432"   | vercel env add DB_PORT production
# equivalently:
vercel env add DB_PORT production --value 5432 --yes
```

Prefer `--type config` for these — Vercel defaults new variables to *Secret*,
which is right for `APP_KEY` but irrelevant for a driver name.

Or import the GitHub repo in the Vercel dashboard — `vercel.json` and
`Dockerfile.vercel` are picked up automatically, and dashboard pushes trigger
a build on every commit.

## After the first deploy

Nothing manual is required. `docker/entrypoint.sh` runs, on every container
boot:

1. `php artisan config:clear`, then `php artisan config:cache` — the cache is
   rebuilt from the runtime environment on each boot, so a redeploy never
   serves a stale cache baked into an older image layer
2. `php artisan migrate --force` — prints `Nothing to migrate` once current
3. `php artisan db:seed --force` — but **only when the `users` table is
   empty** (fresh database); otherwise seeding is skipped and existing
   accounts and passwords are left alone
4. `exec`s FrankenPHP

A failure in step 2 or 3 logs a warning and does **not** stop the container: a
partially migrated app you can inspect beats a container that crash-loops, and
the warnings show up in `vercel logs`.

Vercel containers have **no shell**, so `artisan` cannot be run interactively.
For a one-off command, either add a temporary route as below, or run it from
any machine with the repo and a PHP 8.4 CLI pointed at the same `DB_URL` — the
environment variables are the only thing that ties a command to Vercel.

```php
// routes/web.php  — TEMPORARY, delete immediately after use
Route::get('/__bootstrap-admin', function () {
    abort_unless(request('token') === env('ADMIN_BOOTSTRAP_TOKEN'), 404);

    $status = Artisan::call('app:create-admin', [
        '--name' => env('ADMIN_NAME'),
        '--email' => env('ADMIN_EMAIL'),
        '--password' => env('ADMIN_PASSWORD'),
    ]);

    return response(Artisan::output())->header('Content-Type', 'text/plain');
});
```

The route needs four variables that exist only while it does:
`ADMIN_BOOTSTRAP_TOKEN`, `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`. Set the
token to a long random string, visit `/__bootstrap-admin?token=…`, confirm the
output says `Created administrator`, then **remove the route and delete all
four variables** and redeploy.

## Verify

1. `GET /` returns 200 **and renders the sign-in form** — guests are served
   sign-in at the domain root, so `/login` never has to appear in the address
   bar. `/login` still exists for the redirects the framework issues.
2. `/login` returns 200 **and is styled**. Unstyled means the asset build in
   the image failed.
3. `GET /up` returns 200 — Laravel's health endpoint.
4. Sign in as `admin@barangay.local` / `password`; you should land on
   `/dashboard` with live counts (Residents, Households, Puroks).
5. `https://<project>.vercel.app/.env` must **404**.
6. Check Vercel → Logs: expect `Nothing to migrate` and `Seed complete`, with
   no `ERROR` lines.

## What does not work on Vercel

These are platform limits, not configuration mistakes. They are the reason
`docs/operations/deploy.md` recommends a VPS or shared PHP host for a real
office deployment.

| Feature | Status | Why |
|---|---|---|
| **Resident photo uploads** | ⚠️ Lost on redeploy | `storage/app/private/residents` is on the ephemeral container filesystem. Needs Vercel Blob + a `league/flysystem-aws-s3-v3` adapter, or an external disk. |
| **In-app database backups** | ❌ Unsupported | `BackupService` only writes SQLite/MySQL/MariaDB dumps and rejects other drivers by design. Against Postgres it errors. |
| **Queued notifications** | ⚠️ Inline | No long-running worker on Vercel, so `QUEUE_CONNECTION=sync` sends them during the request instead. A slow SMTP server therefore slows that request. |
| **Scheduled jobs** | ⚠️ Daily only | Hobby allows **one cron fire per day**; the hourly `system:health` schedule cannot be expressed. Pro allows per-minute crons. |
| **`php artisan`** | ❌ No shell | Use the temporary-route pattern above. |

**For a real barangay office deployment, use a VPS or PHP-capable PaaS**
(Railway, Render) where a queue worker, minute-level cron and persistent storage
all exist. Vercel is fine for a demo or a stakeholder walkthrough.