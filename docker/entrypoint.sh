#!/bin/sh
# Container entrypoint: prepare the database, then hand off to FrankenPHP.
set -eu

cd /app

# Vercel injects environment variables at runtime, so the config cache can only
# be built here -- never during the image build.
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

exec frankenphp run --config /etc/frankenphp/Caddyfile