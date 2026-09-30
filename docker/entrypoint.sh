#!/bin/sh
# Container entrypoint: prepare the database, then hand off to FrankenPHP.
set -eu

cd /app

# Vercel injects environment variables at runtime, so the config cache can only
# be built here -- never during the image build. Clear first so a redeploy
# never boots on a stale cache baked into an older layer.
php artisan config:clear || echo "[entrypoint] config:clear failed" >&2
php artisan config:cache || echo "[entrypoint] config:cache failed" >&2

# Safe to run on every boot: already-applied migrations are skipped. Vercel
# scales to zero, so cold starts are almost always a single instance; if two
# do race, the loser logs and the app still serves.
echo "[entrypoint] Running migrations..."
if php artisan migrate --force; then
    echo "[entrypoint] Migrations up to date."
else
    echo "[entrypoint] WARNING: migrations failed -- starting anyway so that" >&2
    echo "[entrypoint] WARNING: the app and its logs stay reachable." >&2
fi

# Seed once, on a fresh database only. The users table is the guard: when it
# already holds rows the accounts (and their current passwords) are left
# alone. Re-seeding on every boot would recreate the demo accounts
# (admin@barangay.local / password) even after an operator deletes them or
# changes their passwords, and on a public URL that is an open admin account.
# DatabaseSeeder itself is idempotent (firstOrCreate), so this guard is about
# *when* to seed, not about surviving a second run.
echo "[entrypoint] Seeding (only when the users table is empty)..."
SEED_GUARD=$(php artisan tinker --execute="print('SEEDGUARD:'.DB::table('users')->count());" 2>&1 | tr -d '\r' | grep -o 'SEEDGUARD:[0-9]*' | cut -d: -f2 | tail -n 1)
if [ "${SEED_GUARD:-}" = "0" ]; then
    echo "[entrypoint] Users table is empty -- seeding demo accounts..."
    if php artisan db:seed --force; then
        echo "[entrypoint] Seed complete."
    else
        echo "[entrypoint] WARNING: seeding failed -- the app will have no accounts." >&2
    fi
elif [ -z "${SEED_GUARD:-}" ]; then
    echo "[entrypoint] WARNING: could not read users count -- attempting seed (may fail safely)." >&2
    if php artisan db:seed --force; then
        echo "[entrypoint] Seed complete."
    else
        echo "[entrypoint] WARNING: seeding failed -- the app will have no accounts." >&2
    fi
else
    echo "[entrypoint] Skipping seed (users table holds ${SEED_GUARD} row(s))."
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile