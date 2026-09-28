# Deploying to Vercel

This app runs on Vercel as a **container service** (FrankenPHP), not as a
serverless PHP function. Vercel has no PHP runtime and no database of its own,
so both are supplied explicitly:

| Piece | Provided by | File |
|---|---|---|
| PHP 8.4 runtime + web server | FrankenPHP image | `Dockerfile.vercel` |
| Document root / routing | Caddy | `Caddyfile` |
| Vercel project wiring | container service + catch-all rewrite | `vercel.json` |
| Database | **external** Postgres (Neon / Supabase / Railway) | env var `DB_URL` |
| First admin account | `php artisan app:create-admin` | env vars `ADMIN_*` |

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

Neon and Supabase both have free tiers; Railway works too. Copy the connection
string — it looks like:

```
postgresql://user:password@host/dbname?sslmode=require
```

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
| `DB_URL` | `postgresql://…` | Your Postgres connection string. |
| `DB_CONNECTION` | `pgsql` | The container also sets this by default. |
| `APP_TRUSTED_PROXIES` | `vercel` | Makes Laravel see the request as HTTPS (see below). |
| `SESSION_SECURE_COOKIE` | `true` | Optional; cookie hardening. |
| `ADMIN_NAME` | your name | One-time, for the bootstrap admin. |
| `ADMIN_EMAIL` | your email | One-time. |
| `ADMIN_PASSWORD` | a strong password | **Delete this after first login.** |

`APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`,
`SESSION_DRIVER=database`, `CACHE_STORE=database` and `QUEUE_CONNECTION=sync`
are already baked into the image.

> **Do not run `php artisan db:seed`.** `DatabaseSeeder` creates
> `admin@barangay.local` / `staff@barangay.local` with the password `password`.
> On a public URL that is an open admin account.

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
vercel link            # create/link the project

vercel env add APP_KEY production
vercel env add APP_URL production
vercel env add DB_URL production
vercel env add APP_TRUSTED_PROXIES production

vercel deploy --prod
```

Or import the GitHub repo in the Vercel dashboard — `vercel.json` and
`Dockerfile.vercel` are picked up automatically.

## After the first deploy

Migrations run automatically on container boot (`docker/entrypoint.sh`), so a
fresh database is migrated for you. Then create the admin account.

Vercel containers have **no shell**, so `artisan` cannot be run directly. Add a
temporary route instead, call it once, then delete it:

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

Set `ADMIN_BOOTSTRAP_TOKEN` to a long random string, visit
`/__bootstrap-admin?token=…`, confirm the output says `Created administrator`,
then **remove the route and delete `ADMIN_PASSWORD` and
`ADMIN_BOOTSTRAP_TOKEN`** and redeploy.

Alternatively, run the same command from any machine that has the repo and a
PHP 8.2+ CLI against the same database — the environment variables are the only
thing that ties it to Vercel.

## Verify

1. `GET /up` returns 200 — Laravel's health endpoint.
2. `/login` returns 200 **and is styled**. Unstyled means the asset build in the
   image failed.
3. Sign in with the admin account.
4. `https://<project>.vercel.app/.env` must **404**.
5. Check Vercel → Logs for errors after exercising the app.

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